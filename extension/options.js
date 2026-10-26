const status = document.getElementById('status')
const apiUrl = document.getElementById('apiUrl')
const say = (text) => (status.textContent = text)

chrome.storage.local.get(['apiUrl', 'token']).then((saved) => {
  if (saved.apiUrl) apiUrl.value = saved.apiUrl
  say(saved.token ? 'Connecté.' : 'Non connecté.')
})

document.getElementById('form').addEventListener('submit', async (event) => {
  event.preventDefault()
  const base = apiUrl.value.replace(/\/+$/, '')
  try {
    // the API origin must be allowed explicitly (requested from this user gesture)
    const granted = await chrome.permissions.request({ origins: [`${new URL(base).origin}/*`] })
    if (!granted) return say("Accès à l'API refusé.")

    const login = await fetch(`${base}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: document.getElementById('email').value, password: document.getElementById('password').value, device_name: 'komoot-extension' }),
    })
    const body = await login.json().catch(() => ({}))
    if (!login.ok) return say(body.message ?? `Connexion refusée (HTTP ${login.status}).`)

    const check = await fetch(`${base}/api/v1/admin/course-imports`, { headers: { Accept: 'application/json', Authorization: `Bearer ${body.token}` } })
    if (check.status === 403) return say("Connexion réussie, mais ce compte n'est pas administrateur (php artisan user:make-admin).")

    await chrome.storage.local.set({ apiUrl: base, token: body.token })
    document.getElementById('password').value = ''
    say('Connecté. Vous pouvez importer depuis une page komoot.')
  } catch {
    say("Impossible de joindre l'API.")
  }
})

document.getElementById('logout').addEventListener('click', async () => {
  await chrome.storage.local.remove('token')
  say('Déconnecté.')
})
