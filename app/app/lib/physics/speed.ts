export interface RiderParams {
  riderKg: number
  bikeKg: number
  crr: number
  cda: number
  rho: number
}

export const DEFAULT_PARAMS: RiderParams = {
  riderKg: 75,
  bikeKg: 9,
  crr: 0.004,
  cda: 0.32,
  rho: 1.2,
}

const G = 9.81
const V_MAX = 30 // m/s, bracket for bisection

/** Power (W) needed to hold speed v (m/s) on a grade given in percent. */
function powerAt(v: number, gradePct: number, p: RiderParams): number {
  const theta = Math.atan(gradePct / 100)
  const mass = p.riderKg + p.bikeKg
  return v * mass * G * (Math.sin(theta) + p.crr * Math.cos(theta)) + 0.5 * p.rho * p.cda * v ** 3
}

/**
 * Steady-state speed (m/s) for a given power (W) and grade (%).
 * On steep descents with little power the net force is positive at v=0 and
 * the root is the terminal speed; above V_MAX we cap.
 */
export function solveSpeed(powerW: number, gradePct: number, p: RiderParams = DEFAULT_PARAMS): number {
  const target = Math.max(powerW, 0)
  if (powerAt(V_MAX, gradePct, p) <= target) return V_MAX
  // powerAt is not monotonic on descents (negative at low v): bisect only on the increasing part
  let lo = 0
  let hi = V_MAX
  const theta = Math.atan(gradePct / 100)
  const slope = Math.sin(theta) + p.crr * Math.cos(theta)
  if (slope < 0) {
    // minimum at v* where d/dv = 0 → m g slope + 1.5 ρ CdA v² = 0
    lo = Math.sqrt((-(p.riderKg + p.bikeKg) * G * slope) / (1.5 * p.rho * p.cda))
  }
  for (let i = 0; i < 50; i++) {
    const mid = (lo + hi) / 2
    if (powerAt(mid, gradePct, p) < target) lo = mid
    else hi = mid
  }
  return (lo + hi) / 2
}

/** Advance speed one step towards steady state with first-order inertia (tau seconds). */
export function stepSpeed(
  current: number,
  powerW: number,
  gradePct: number,
  dtS: number,
  p: RiderParams = DEFAULT_PARAMS,
  tauS = 2,
): number {
  const target = solveSpeed(powerW, gradePct, p)
  return current + (target - current) * (1 - Math.exp(-dtS / tauS))
}
