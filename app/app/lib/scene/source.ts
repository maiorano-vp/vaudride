/** Where the 3D scene gets its relief and surroundings from. */
export enum SceneSource {
  /** swisstopo terrain + SWISSIMAGE + 3D buildings and vegetation (default, open data). */
  Swisstopo = 'swisstopo',
  /** swisstopo terrain and imagery only: lighter on modest machines. */
  Light = 'light',
  /** Google Photorealistic 3D Tiles with a Google Maps API key (billed by Google). */
  Google = 'google',
  /** The same Google tiles through Cesium ion, with an ion access token. */
  GoogleIon = 'googleIon',
  /** swisstopo around the rider (sharp buildings), Google photogrammetry further away. */
  Mixed = 'mixed',
}

/** Which credentials are configured: Google sources are only offered when theirs is. */
export interface SceneKeys {
  google: boolean
  ion: boolean
}

export const parseSceneSource = (value: unknown): SceneSource | undefined =>
  Object.values(SceneSource).find((s) => s === value)

/** Google's mesh replaces the globe. */
export const isPhotorealistic = (s: SceneSource) => s === SceneSource.Google || s === SceneSource.GoogleIon

/** Sources that stream Google's mesh. */
export const usesGoogle = (s: SceneSource) => isPhotorealistic(s) || s === SceneSource.Mixed

/** Sources that draw swisstopo's 3D buildings and trees. */
export const usesSwissBuildings = (s: SceneSource) => s === SceneSource.Swisstopo || s === SceneSource.Mixed

export const availableSceneSources = (keys: SceneKeys): SceneSource[] =>
  Object.values(SceneSource).filter(
    (s) =>
      (s !== SceneSource.Google || keys.google) &&
      (s !== SceneSource.GoogleIon || keys.ion) &&
      (s !== SceneSource.Mixed || keys.google || keys.ion),
  )

/** The stored choice if it can be used, the default otherwise. */
export const resolveSceneSource = (stored: unknown, keys: SceneKeys): SceneSource => {
  const wanted = parseSceneSource(stored)
  return wanted && availableSceneSources(keys).includes(wanted) ? wanted : SceneSource.Swisstopo
}
