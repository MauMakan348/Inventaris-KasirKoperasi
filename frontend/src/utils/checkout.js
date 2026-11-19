export function buildCheckoutPayload(lines, paymentMethod, amountPaid) {
  const payload = {
    payment_method: paymentMethod,
    items: lines.map((line) => ({
      item_id: line.itemId,
      quantity: line.quantity,
    })),
  }

  if (paymentMethod === 'CASH' && amountPaid !== '' && amountPaid !== null && amountPaid !== undefined) {
    const paid = Number(amountPaid)

    if (Number.isInteger(paid)) {
      payload.amount_paid = paid
    }
  }

  return payload
}
