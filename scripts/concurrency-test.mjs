import process from 'node:process'

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000/api'
const DEMO_PASSWORD = process.env.DEMO_PASSWORD

const EMAILS = {
  ADMIN: process.env.ADMIN_EMAIL || 'admin@example.test',
  CASHIER: process.env.CASHIER_EMAIL || 'cashier1@example.test',
  MEMBER: process.env.MEMBER_EMAIL || 'member1@example.test',
}

const REQUEST_TIMEOUT_MS = 30000
const ITEM_PRICE = 5000
const CASH_AMOUNT = 1000000

const SCENARIOS = [
  { code: 'HP-02', title: 'Rebutan stok terakhir (POS vs ONLINE)', rounds: 10 },
  { code: 'HP-03', title: 'Stok 5, 20 request paralel', rounds: 5 },
  { code: 'HP-04', title: 'Urutan barang berlawanan (deadlock)', rounds: 5 },
  { code: 'HP-08', title: 'All-or-Nothing di bawah beban', rounds: 5 },
]

function fail(message) {
  console.error(`GAGAL: ${message}`)
  process.exit(1)
}

function readRoundsOverride() {
  const raw = process.env.ROUNDS

  if (raw === undefined || raw === '') {
    return null
  }

  const rounds = Number(raw)

  if (!Number.isInteger(rounds) || rounds < 1) {
    fail('ROUNDS harus berupa bilangan bulat minimal 1.')
  }

  return rounds
}

const ROUNDS_OVERRIDE = readRoundsOverride()

if (!DEMO_PASSWORD) {
  fail('DEMO_PASSWORD wajib diisi. Contoh: DEMO_PASSWORD=demo-password node scripts/concurrency-test.mjs')
}

if (typeof fetch !== 'function' || typeof AbortSignal.timeout !== 'function') {
  fail('Node.js minimal versi 18 diperlukan (fetch dan AbortSignal.timeout).')
}

const runId = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.slice(-10)

async function request(path, { method = 'GET', token, body } = {}) {
  const headers = { Accept: 'application/json' }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  try {
    const response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    })

    const text = await response.text()
    let data = null

    if (text !== '') {
      try {
        data = JSON.parse(text)
      } catch {
        data = { raw: text }
      }
    }

    return { status: response.status, data }
  } catch (error) {
    return {
      status: 0,
      networkError: error.name === 'TimeoutError' ? `timeout ${REQUEST_TIMEOUT_MS} ms` : error.message,
    }
  }
}

async function login(role) {
  const email = EMAILS[role]
  const result = await request('/auth/login', {
    method: 'POST',
    body: { email, password: DEMO_PASSWORD },
  })

  if (result.status === 429) {
    console.error(`GAGAL: login ${role} kena pembatas laju (429). Tunggu satu menit lalu ulangi.`)
    process.exit(1)
  }

  if (result.status !== 200 || !result.data?.data?.token) {
    const code = result.data?.error?.code || 'TANPA_KODE'
    const message = result.data?.error?.message || result.networkError || 'respons tidak dikenal'
    fail(`login ${role} gagal (HTTP ${result.status}, ${code}): ${message}`)
  }

  return result.data.data.token
}

function uniqueSku(scenarioCode, round, itemKey) {
  return `CT-${runId}-${scenarioCode.replace('-', '')}-${round}-${itemKey}`
}

async function createItem(adminToken, scenarioCode, round, itemKey, stock) {
  const result = await request('/items', {
    method: 'POST',
    token: adminToken,
    body: {
      sku: uniqueSku(scenarioCode, round, itemKey),
      name: `Uji ${scenarioCode} ronde ${round} ${itemKey}`,
      price: ITEM_PRICE,
      stock,
    },
  })

  if (result.status !== 201) {
    const code = result.data?.error?.code || 'TANPA_KODE'
    const message = result.data?.error?.message || result.networkError || 'respons tidak dikenal'
    throw new Error(`POST /items gagal (HTTP ${result.status}, ${code}): ${message}`)
  }

  return result.data.data
}

function checkoutPayload(actor, itemLines, itemKeyById) {
  const items = itemLines.map(([itemKey, quantity]) => ({
    item_id: itemKeyById[itemKey],
    quantity,
  }))

  if (actor === 'CASHIER') {
    return { payment_method: 'CASH', amount_paid: CASH_AMOUNT, items }
  }

  return { payment_method: 'TRANSFER', items }
}

function classify(result) {
  if (result.status === 201) {
    return 'success'
  }

  if (result.status === 409 && result.data?.error?.code === 'INSUFFICIENT_STOCK') {
    return 'expected'
  }

  return 'unexpected'
}

function describeUnexpected(result) {
  if (result.status === 0) {
    return `jaringan/timeout: ${result.networkError}`
  }

  const code = result.data?.error?.code || 'TANPA_KODE'
  const message = result.data?.error?.message || result.data?.raw || 'tanpa pesan'

  return `HTTP ${result.status} ${code}: ${message}`
}

async function runSimultaneously(tasks) {
  let releaseGate
  const gate = new Promise((resolve) => {
    releaseGate = resolve
  })

  const start = performance.now()

  const pending = tasks.map(async (task) => {
    await gate
    return task()
  })

  releaseGate()
  const settled = await Promise.allSettled(pending)
  const durationMs = performance.now() - start

  const results = settled.map((entry) => {
    if (entry.status === 'fulfilled') {
      return entry.value
    }

    return { status: 0, networkError: String(entry.reason) }
  })

  return { results, durationMs }
}

async function readItemState(adminToken, itemId) {
  const [itemResult, movementsResult] = await Promise.all([
    request(`/items/${itemId}`, { token: adminToken }),
    request(`/items/${itemId}/movements`, { token: adminToken }),
  ])

  if (itemResult.status !== 200) {
    throw new Error(`GET /items/${itemId} gagal (HTTP ${itemResult.status}).`)
  }

  if (movementsResult.status !== 200) {
    throw new Error(`GET /items/${itemId}/movements gagal (HTTP ${movementsResult.status}).`)
  }

  return {
    stock: itemResult.data.data.stock,
    movements: movementsResult.data.data,
  }
}

async function verifyItem(adminToken, itemId, expectedStock, expectedSaleMovements) {
  const state = await readItemState(adminToken, itemId)
  const errors = []

  const sales = state.movements.filter(
    (movement) => typeof movement.type === 'string' && movement.type.startsWith('SALE_'),
  )
  const ledgerSum = state.movements.reduce(
    (sum, movement) => sum + movement.quantity_change,
    0,
  )

  if (state.stock < 0) {
    errors.push(`stok akhir negatif (${state.stock})`)
  }

  if (state.stock !== expectedStock) {
    errors.push(`stok akhir ${state.stock}, seharusnya ${expectedStock}`)
  }

  if (sales.length !== expectedSaleMovements) {
    errors.push(`mutasi SALE_* ${sales.length}, seharusnya ${expectedSaleMovements}`)
  }

  if (ledgerSum !== state.stock) {
    errors.push(`jumlah quantity_change ${ledgerSum}, seharusnya sama dengan stok akhir ${state.stock}`)
  }

  return { errors, stock: state.stock, saleCount: sales.length, ledgerSum }
}

function formatCounts(counts) {
  return `201=${counts.success} 409=${counts.expected} tak terduga=${counts.unexpected}`
}

async function runRound({ scenario, round, adminToken, cashierToken, memberToken }) {
  const tokens = { CASHIER: cashierToken, MEMBER: memberToken }

  let itemSpecs
  let expectedSuccess
  let expectedExpected
  let expectedStocks
  let expectedSaleMovements
  let requestCount
  let buildRequests

  if (scenario.code === 'HP-02') {
    itemSpecs = [{ key: 'A', stock: 1 }]
    expectedSuccess = 1
    expectedExpected = 1
    expectedStocks = { A: 0 }
    expectedSaleMovements = { A: 1 }
    requestCount = 2
    buildRequests = (itemKeyById) => [
      { actor: 'CASHIER', lines: [['A', 1]] },
      { actor: 'MEMBER', lines: [['A', 1]] },
    ]
  } else if (scenario.code === 'HP-03') {
    itemSpecs = [{ key: 'A', stock: 5 }]
    expectedSuccess = 5
    expectedExpected = 15
    expectedStocks = { A: 0 }
    expectedSaleMovements = { A: 5 }
    requestCount = 20
    buildRequests = (itemKeyById) =>
      Array.from({ length: requestCount }, (_, index) => ({
        actor: index % 2 === 0 ? 'CASHIER' : 'MEMBER',
        lines: [['A', 1]],
      }))
  } else if (scenario.code === 'HP-04') {
    itemSpecs = [
      { key: 'A', stock: 1000 },
      { key: 'B', stock: 1000 },
    ]
    expectedSuccess = 20
    expectedExpected = 0
    expectedStocks = { A: 980, B: 980 }
    expectedSaleMovements = { A: 20, B: 20 }
    requestCount = 20
    buildRequests = () =>
      Array.from({ length: requestCount }, (_, index) => ({
        actor: index % 2 === 0 ? 'CASHIER' : 'MEMBER',
        lines: index % 4 < 2 ? [['A', 1], ['B', 1]] : [['B', 1], ['A', 1]],
      }))
  } else {
    itemSpecs = [
      { key: 'A', stock: 1000 },
      { key: 'B', stock: 1 },
    ]
    expectedSuccess = 1
    expectedExpected = 9
    expectedStocks = { A: 999, B: 0 }
    expectedSaleMovements = { A: 1, B: 1 }
    requestCount = 10
    buildRequests = () =>
      Array.from({ length: requestCount }, (_, index) => ({
        actor: index % 2 === 0 ? 'CASHIER' : 'MEMBER',
        lines: [['A', 1], ['B', 1]],
      }))
  }

  const itemKeyById = {}
  const itemIds = {}

  for (const spec of itemSpecs) {
    const item = await createItem(adminToken, scenario.code, round, spec.key, spec.stock)
    itemIds[spec.key] = item.id
    itemKeyById[spec.key] = item.id
  }

  const requests = buildRequests(itemKeyById)
  const tasks = requests.map((requestSpec) => () =>
    request('/orders', {
      method: 'POST',
      token: tokens[requestSpec.actor],
      body: checkoutPayload(requestSpec.actor, requestSpec.lines, itemKeyById),
    }),
  )

  const { results, durationMs } = await runSimultaneously(tasks)
  const counts = { success: 0, expected: 0, unexpected: 0 }
  const unexpectedDetails = []
  const winners = []

  results.forEach((result, index) => {
    const kind = classify(result)

    if (kind === 'success') {
      counts.success += 1

      if (scenario.code === 'HP-02') {
        winners.push(result.data?.data?.channel || requests[index].actor)
      }
    } else if (kind === 'expected') {
      counts.expected += 1
    } else {
      counts.unexpected += 1
      unexpectedDetails.push(describeUnexpected(result))
    }
  })

  const errors = []

  if (counts.success !== expectedSuccess) {
    errors.push(`jumlah 201 ${counts.success}, seharusnya ${expectedSuccess}`)
  }

  if (counts.expected !== expectedExpected) {
    errors.push(`jumlah 409 ${counts.expected}, seharusnya ${expectedExpected}`)
  }

  if (counts.unexpected !== 0) {
    errors.push(`ada ${counts.unexpected} respons tak terduga`)
  }

  if (results.length !== requestCount) {
    errors.push(`jumlah respons ${results.length}, seharusnya ${requestCount}`)
  }

  const verifiedStocks = {}

  for (const spec of itemSpecs) {
    const verification = await verifyItem(
      adminToken,
      itemIds[spec.key],
      expectedStocks[spec.key],
      expectedSaleMovements[spec.key],
    )

    verifiedStocks[spec.key] = verification.stock
    errors.push(...verification.errors.map((error) => `${spec.key}: ${error}`))
  }

  const winnerText = winners.length > 0 ? ` | pemenang ${winners.join(',')}` : ''
  const stockText = itemSpecs
    .map((spec) => `${spec.key}=${verifiedStocks[spec.key]}`)
    .join(' ')
  const status = errors.length === 0 ? 'PASS' : 'FAIL'

  console.log(
    `${scenario.code} ronde ${round}/${scenario.rounds}: ${status} | ${formatCounts(counts)} | stok akhir ${stockText} | ${Math.round(durationMs)} ms${winnerText}`,
  )

  for (const detail of unexpectedDetails) {
    console.log(`  tak terduga: ${detail}`)
  }

  for (const error of errors) {
    console.log(`  kegagalan: ${error}`)
  }

  return {
    pass: errors.length === 0,
    counts,
    durationMs,
    stockText,
    winnerText,
    errors,
  }
}

async function main() {
  const roundsOverrideText = ROUNDS_OVERRIDE === null ? 'default' : String(ROUNDS_OVERRIDE)

  console.log('Uji konkurensi checkout Smart Koperasi')
  console.log(`BASE_URL: ${BASE_URL}`)
  console.log(`Run ID: ${runId}`)
  console.log(`Ronde: ${roundsOverrideText}`)
  console.log('')
  console.log('PENTING: server harus berjalan dengan banyak worker, misalnya:')
  console.log('  PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload --port=8000')
  console.log('Laravel 13 butuh --no-reload; tanpa flag itu hanya satu server dibuat.')
  console.log('php artisan serve biasa hanya melayani satu request sekali jalan sehingga uji tidak bermakna.')
  console.log('Catatan Windows: PHP_CLI_SERVER_WORKERS tidak didukung; gunakan WSL, Laragon, atau Nginx + PHP-FPM.')
  console.log('')
  console.log('Login tiga akun...')

  const [adminToken, cashierToken, memberToken] = await Promise.all([
    login('ADMIN'),
    login('CASHIER'),
    login('MEMBER'),
  ])

  console.log('Login berhasil.')
  console.log('')

  const scenarioSummaries = []
  let allPass = true

  for (const scenario of SCENARIOS) {
    scenario.rounds = ROUNDS_OVERRIDE ?? scenario.rounds

    const summary = {
      code: scenario.code,
      title: scenario.title,
      rounds: scenario.rounds,
      passedRounds: 0,
      failedRounds: 0,
      success: 0,
      expected: 0,
      unexpected: 0,
      durationMs: 0,
      winners: [],
    }

    console.log(`${scenario.code} - ${scenario.title}`)

    for (let round = 1; round <= scenario.rounds; round += 1) {
      try {
        const result = await runRound({
          scenario,
          round,
          adminToken,
          cashierToken,
          memberToken,
        })

        summary.success += result.counts.success
        summary.expected += result.counts.expected
        summary.unexpected += result.counts.unexpected
        summary.durationMs += result.durationMs

        if (result.pass) {
          summary.passedRounds += 1
        } else {
          summary.failedRounds += 1
          allPass = false
        }

        if (scenario.code === 'HP-02' && result.winnerText !== '') {
          summary.winners.push(result.winnerText.replace(' | pemenang ', ''))
        }
      } catch (error) {
        summary.failedRounds += 1
        allPass = false
        console.log(`${scenario.code} ronde ${round}/${scenario.rounds}: FAIL | ${error.message}`)
      }
    }

    console.log('')
    scenarioSummaries.push(summary)
  }

  console.log('Tabel ringkas')
  console.log('| Skenario | Ronde | Lulus | Gagal | 201 | 409 | Tak terduga | Durasi total | Rata-rata |')
  console.log('|---|---:|---:|---:|---:|---:|---:|---:|---:|')

  for (const summary of scenarioSummaries) {
    const average = summary.rounds === 0 ? 0 : summary.durationMs / summary.rounds
    console.log(
      `| ${summary.code} | ${summary.rounds} | ${summary.passedRounds} | ${summary.failedRounds} | ${summary.success} | ${summary.expected} | ${summary.unexpected} | ${Math.round(summary.durationMs)} ms | ${Math.round(average)} ms |`,
    )
  }

  const hp02 = scenarioSummaries.find((summary) => summary.code === 'HP-02')

  if (hp02 && hp02.winners.length > 0) {
    console.log('')
    console.log(`Channel pemenang HP-02 per ronde: ${hp02.winners.join(', ')}`)
  }

  console.log('')
  console.log('Catatan: sisa barang uji dibiarkan. Reset dengan: php artisan migrate:fresh --seed')

  if (!allPass) {
    console.log('')
    console.log('HASIL AKHIR: GAGAL. Ada ronde yang tidak memenuhi harapan.')
    process.exitCode = 1
    return
  }

  console.log('HASIL AKHIR: SEMUA LOLOS.')
  process.exitCode = 0
}

main().catch((error) => {
  console.error(`GAGAL: ${error.message}`)
  process.exitCode = 1
})
