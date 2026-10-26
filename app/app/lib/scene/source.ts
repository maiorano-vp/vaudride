/** Where the 3D scene gets its relief and surroundings from. */
export enum SceneSource {
  /** swisstopo terrain + SWISSIMAGE + 3D buildings and vegetation (default, open data). */
  Swisstopo = 'swisstopo',
  /** swisstopo terrain and imagery only: lighter on modest machines. */
  Light = 'light',
  /** Google Photorealistic 3D Tiles: textured photogrammetry, needs a Google Maps API key. */
  Google = 'google',
}

export const parseSceneSource = (value: unknown): SceneSource | undefined =>
  Object.values(SceneSource).find((s) => s === value)

/** Google is only offered when a key is configured. */
export const availableSceneSources = (hasGoogleKey: boolean): SceneSource[] =>
  Object.values(SceneSource).filter((s) => s !== SceneSource.Google || hasGoogleKey)

/** The stored choice if it can be used, the default otherwise. */
export const resolveSceneSource = (stored: unknown, hasGoogleKey: boolean): SceneSource => {
  const wanted = parseSceneSource(stored)
  return wanted && availableSceneSources(hasGoogleKey).includes(wanted) ? wanted : SceneSource.Swisstopo
}
