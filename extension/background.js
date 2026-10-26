// Sends the captured GPX to the Vaud Ride API. Done here (not in the page) so komoot's CORS rules do not apply.
chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.type !== 'import') return false
  importGpx(message).then(sendResponse, (error) => sendResponse({ ok: false, error: String(error?.message ?? error) }))
  return true
})

async function importGpx({ gpx, sourceUrl, title }) {
  const { apiUrl, token } = await chrome.storage.local.get(['apiUrl', 'token'])
  if (!apiUrl || !token) return { ok: false, error: "Extension non configurée : ouvrez ses options et connectez-vous à l'API." }

  const response = await fetch(`${apiUrl}/api/v1/admin/course-imports`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` },
    body: JSON.stringify({ gpx, source: 'komoot', source_url: sourceUrl, title }),
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    const detail = response.status === 403 ? "ce compte n'est pas administrateur" : (body.message ?? `HTTP ${response.status}`)
    return { ok: false, error: detail }
  }
  return { ok: true, import: body.data }
}
