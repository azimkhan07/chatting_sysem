/**
 * The contact-sync opt-in, in one place.
 *
 * The preference lives in localStorage rather than on the account because the
 * promise is "we do not keep your phone book" — a server-side copy of that flag
 * would be a server-side copy of the thing the flag exists to avoid. Both the
 * chat Discover rail and the Settings screen read it through here so the two
 * cannot drift apart.
 */

export const CONTACTS_STORAGE_KEY = 'amtech:contact-sync'

/** Subscribe to changes from anywhere in the app, including other components. */
const listeners = new Set<(enabled: boolean) => void>()

function isContactsEnabled(): boolean {
  return localStorage.getItem(CONTACTS_STORAGE_KEY) === 'on'
}

export function getContactSync(): boolean {
  return isContactsEnabled()
}

export function setContactSync(enabled: boolean): boolean {
  localStorage.setItem(CONTACTS_STORAGE_KEY, enabled ? 'on' : 'off')
  listeners.forEach((listener) => listener(enabled))
  return enabled
}

export function watchContactSync(listener: (enabled: boolean) => void): () => void {
  listeners.add(listener)
  // Covers the other tab; the same-tab cases go through setContactSync.
  const onStorage = (event: StorageEvent) => {
    if (event.key === CONTACTS_STORAGE_KEY) listener(isContactsEnabled())
  }
  window.addEventListener('storage', onStorage)
  return () => {
    listeners.delete(listener)
    window.removeEventListener('storage', onStorage)
  }
}
