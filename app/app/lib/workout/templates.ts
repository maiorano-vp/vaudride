import { repeat, type Workout } from './workout'

const min = (m: number) => m * 60

/** Workouts shipped with the app; the percentages are of the rider's FTP. */
export const TEMPLATES: Workout[] = [
  {
    id: 'threshold-5x4',
    name: { fr: 'Seuil 5 × 4 min', de: 'Schwelle 5 × 4 Min.', en: 'Threshold 5 × 4 min' },
    steps: [
      { durationS: min(10), ftpPct: 55 },
      ...repeat(5, { durationS: min(4), ftpPct: 100 }, { durationS: min(3), ftpPct: 50 }),
      { durationS: min(5), ftpPct: 50 },
    ],
  },
  {
    id: 'sweet-spot-2x20',
    name: { fr: 'Sweet spot 2 × 20 min', de: 'Sweet Spot 2 × 20 Min.', en: 'Sweet spot 2 × 20 min' },
    steps: [
      { durationS: min(10), ftpPct: 55 },
      { durationS: min(20), ftpPct: 90 },
      { durationS: min(5), ftpPct: 50 },
      { durationS: min(20), ftpPct: 90 },
      { durationS: min(5), ftpPct: 50 },
    ],
  },
  {
    id: 'pyramid',
    name: { fr: 'Pyramide', de: 'Pyramide', en: 'Pyramid' },
    steps: [
      { durationS: min(10), ftpPct: 55 },
      ...[1, 2, 3, 4, 3, 2, 1].flatMap((m) => [
        { durationS: min(m), ftpPct: 105 },
        { durationS: min(2), ftpPct: 50 },
      ]),
      { durationS: min(5), ftpPct: 50 },
    ],
  },
  {
    id: 'tempo-40',
    name: { fr: 'Tempo 40 min', de: 'Tempo 40 Min.', en: 'Tempo 40 min' },
    steps: [
      { durationS: min(10), ftpPct: 55 },
      { durationS: min(40), ftpPct: 80 },
      { durationS: min(5), ftpPct: 50 },
    ],
  },
  {
    id: 'endurance-60',
    name: { fr: 'Endurance 60 min', de: 'Grundlage 60 Min.', en: 'Endurance 60 min' },
    steps: [
      { durationS: min(5), ftpPct: 50 },
      { durationS: min(55), ftpPct: 65 },
    ],
  },
]
