// Adds a "Send to Vaud Ride" button on komoot tour pages. It clicks komoot's own "Download GPX" control
// (which needs the user to be logged in to komoot), captures the GPX via inject.js and hands it to the background worker.
;(() => {
  if (!/\/(smart)?tour\//.test(location.pathname)) return

  const host = document.createElement('div')
  host.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2147483647'
  const root = host.attachShadow({ mode: 'open' })
  root.innerHTML = `
    <style>
      button { font: 600 14px system-ui, sans-serif; padding: 10px 16px; border: 0; border-radius: 8px; background: #0a7d3b; color: #fff; cursor: pointer; box-shadow: 0 2px 8px #0004 }
      button:disabled { opacity: .6; cursor: wait }
      p { margin: 8px 0 0; max-width: 280px; padding: 8px 12px; font: 13px system-ui, sans-serif; background: #fff; color: #222; border-radius: 8px; box-shadow: 0 2px 8px #0004 }
      p[hidden] { display: none }
    </style>
    <button type="button">Envoyer vers Vaud Ride</button>
    <p hidden></p>`
  const button = root.querySelector('button')
  const note = root.querySelector('p')
  document.documentElement.append(host)

  const say = (text) => {
    note.textContent = text
    note.hidden = false
  }

  const findGpxControl = () =>
    [...document.querySelectorAll('button, a, [role="menuitem"]')].find((element) =>
      /gpx/i.test(element.textContent ?? '') && /(télécharger|download|herunterladen|exporter|export)/i.test(element.textContent ?? ''),
    )

  const waitForGpx = () =>
    new Promise((resolve, reject) => {
      const listener = (event) => {
        if (event.source !== window || event.data?.source !== 'vaudride-page') return
        window.removeEventListener('message', listener)
        if (event.data.type === 'gpx') resolve(event.data.gpx)
        else reject(new Error('timeout'))
      }
      window.addEventListener('message', listener)
      window.postMessage({ source: 'vaudride-content', type: 'capture' }, window.location.origin)
    })

  button.addEventListener('click', async () => {
    const control = findGpxControl()
    if (!control) {
      say('Bouton « Télécharger GPX » introuvable : ouvrez le menu du parcours puis réessayez.')
      return
    }

    button.disabled = true
    say('Récupération du GPX…')
    try {
      const pending = waitForGpx()
      control.click()
      const gpx = await pending
      const title = document.title.split(' | ')[0].trim()
      const result = await chrome.runtime.sendMessage({ type: 'import', gpx, sourceUrl: location.origin + location.pathname, title })
      if (result?.ok) {
        const summary = result.import.summary
        say(`Importé : ${result.import.title} — ${(summary.distance_m / 1000).toFixed(1)} km, ${Math.round(summary.elevation_gain_m)} m D+. À vérifier et à libérer côté admin.`)
      } else {
        say(`Échec de l'import : ${result?.error ?? 'erreur inconnue'}`)
      }
    } catch {
      say('Aucun GPX reçu. Connectez-vous à komoot (le téléchargement l’exige), puis réessayez.')
    } finally {
      button.disabled = false
    }
  })
})()
