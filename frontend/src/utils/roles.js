export const ROLE_LABELS = {
  ADMIN: 'Admin',
  CASHIER: 'Kasir',
  MEMBER: 'Anggota',
}

export const HOME_PATH_BY_ROLE = {
  ADMIN: '/admin/items',
  CASHIER: '/pos',
  MEMBER: '/catalog',
}

export function homePathForRole(role) {
  return HOME_PATH_BY_ROLE[role] || '/login'
}
