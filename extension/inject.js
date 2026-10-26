// Runs inside komoot's page (MAIN world). While "capturing", it grabs the GPX that komoot's own
// "Download GPX" button produces and swallows the file save, so the GPX goes to Vaud Ride instead of the disk.
;(() => {
  if (window.__vaudRide) return
  const state = { capturing: false, timer: undefined }
  window.__vaudRide = state

  const looksLikeGpx = (text) => typeof text === 'string' && text.includes('<gpx')
  const deliver = (gpx) => {
    if (!state.capturing) return
    state.capturing = false
    clearTimeout(state.timer)
    window.postMessage({ source: 'vaudride-page', type: 'gpx', gpx }, window.location.origin)
  }
  const fromUrl = (url) =>
    fetch(url, { credentials: 'include' })
      .then((response) => response.text())
      .then((text) => looksLikeGpx(text) && deliver(text))
      .catch(() => {})

  window.addEventListener('message', (event) => {
    const data = event.data
    if (event.source !== window || data?.source !== 'vaudride-content' || data.type !== 'capture') return
    state.capturing = true
    clearTimeout(state.timer)
    state.timer = setTimeout(() => {
      state.capturing = false
      window.postMessage({ source: 'vaudride-page', type: 'timeout' }, window.location.origin)
    }, 15000)
  })

  // GPX built in the page and offered as a blob download
  const createObjectURL = URL.createObjectURL
  URL.createObjectURL = function (object) {
    if (state.capturing && object instanceof Blob) object.text().then((text) => looksLikeGpx(text) && deliver(text))
    return createObjectURL.call(this, object)
  }

  // the download itself: swallow it while capturing (a plain link to a server file is fetched instead)
  const anchorClick = HTMLAnchorElement.prototype.click
  HTMLAnchorElement.prototype.click = function (...args) {
    if (state.capturing && (this.hasAttribute('download') || /gpx/i.test(this.href))) {
      if (!this.href.startsWith('blob:')) fromUrl(this.href)
      return undefined
    }
    return anchorClick.apply(this, args)
  }

  // GPX delivered by an API call
  const nativeFetch = window.fetch
  window.fetch = async function (...args) {
    const response = await nativeFetch.apply(this, args)
    if (state.capturing && /gpx|xml/i.test(response.headers.get('content-type') ?? '')) {
      response.clone().text().then((text) => looksLikeGpx(text) && deliver(text))
    }
    return response
  }
})()
