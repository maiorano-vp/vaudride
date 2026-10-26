"""Build dist/<slug>/profile.json from courses/<slug>/route.gpx and swissALTI3D tiles."""

import argparse
import json
from pathlib import Path
from typing import Any

import gpxpy
import numpy as np
import rasterio
import yaml
from pyproj import Transformer
from shapely.geometry import LineString

ROOT = Path(__file__).resolve().parent.parent
STEP_M = 5
SMOOTH_M = 50
GRADE_MIN, GRADE_MAX = -15.0, 20.0

to_lv95 = Transformer.from_crs(4326, 2056, always_xy=True)
to_wgs84 = Transformer.from_crs(2056, 4326, always_xy=True)


def resample(xs: np.ndarray, ys: np.ndarray, step: float = STEP_M) -> tuple[np.ndarray, ...]:
    line = LineString(np.column_stack([xs, ys]))
    d = np.append(np.arange(0, line.length, step), line.length)
    pts = np.array([line.interpolate(v).coords[0] for v in d])
    return d, pts[:, 0], pts[:, 1]


class Dem:
    """swissALTI3D GeoTIFF tiles with bilinear sampling (LN02 altitudes)."""

    def __init__(self, folder: Path) -> None:
        self.tiles = []
        for tif in sorted(folder.glob("*.tif")):
            with rasterio.open(tif) as ds:
                self.tiles.append((ds.bounds, ds.transform, ds.read(1).astype("float64")))
        if not self.tiles:
            raise FileNotFoundError(f"no .tif tiles in {folder}")

    def sample(self, x: float, y: float) -> float:
        for b, t, arr in self.tiles:
            if b.left <= x < b.right and b.bottom < y <= b.top:
                # pixel centres sit at +0.5 in pixel space; shift so bilinear weights are exact
                c = (x - t.c) / t.a - 0.5
                r = (y - t.f) / t.e - 0.5
                c0, r0 = int(np.floor(c)), int(np.floor(r))
                c0, r0 = min(max(c0, 0), arr.shape[1] - 2), min(max(r0, 0), arr.shape[0] - 2)
                fc, fr = min(max(c - c0, 0), 1), min(max(r - r0, 0), 1)
                top = arr[r0, c0] * (1 - fc) + arr[r0, c0 + 1] * fc
                bot = arr[r0 + 1, c0] * (1 - fc) + arr[r0 + 1, c0 + 1] * fc
                return float(top * (1 - fr) + bot * fr)
        raise ValueError(f"point ({x:.0f}, {y:.0f}) outside DEM tiles")


def apply_overrides(d: np.ndarray, ele: np.ndarray, overrides: list[dict[str, float]]) -> np.ndarray:
    """Replace altitude between from_d and to_d (bridge/tunnel) by a straight line."""
    ele = ele.copy()
    for o in overrides:
        i, j = np.searchsorted(d, o["from_d"]), np.searchsorted(d, o["to_d"])
        ele[i : j + 1] = np.interp(d[i : j + 1], [d[i], d[j]], [ele[i], ele[j]])
    return ele


def smooth(values: np.ndarray, window_m: float = SMOOTH_M, step: float = STEP_M) -> np.ndarray:
    n = int(window_m / step) | 1  # odd window
    pad = np.pad(values, n // 2, mode="edge")
    return np.convolve(pad, np.ones(n) / n, mode="valid")


def build(slug: str, dem_dir: Path, out_dir: Path) -> dict[str, Any]:
    course_dir = ROOT / "courses" / slug
    cfg = yaml.safe_load((course_dir / "overrides.yaml").read_text()) or {}
    gpx = gpxpy.parse((course_dir / "route.gpx").read_text())
    pts = [(p.longitude, p.latitude) for t in gpx.tracks for s in t.segments for p in s.points]
    lons, lats = zip(*pts, strict=True)
    x, y = to_lv95.transform(list(lons), list(lats))
    d, x, y = resample(np.array(x), np.array(y))

    dem = Dem(dem_dir)
    ele = np.array([dem.sample(a, b) for a, b in zip(x, y, strict=True)])
    ele = smooth(apply_overrides(d, ele, cfg.get("overrides", [])))
    grade = np.clip(np.gradient(ele, d) * 100, GRADE_MIN, GRADE_MAX)
    lon, lat = to_wgs84.transform(x, y)

    pois_file = course_dir / "pois.json"
    profile = {
        "slug": slug,
        "name": cfg.get("name", slug),
        "version": cfg.get("version", 1),
        "distance_m": round(float(d[-1]), 1),
        "elevation_gain_m": round(float(np.clip(np.diff(ele), 0, None).sum()), 1),
        "points": [
            {"d": round(float(a), 1), "lat": round(float(b), 6), "lon": round(float(c), 6),
             "ele": round(float(e), 1), "grade": round(float(g), 2)}
            for a, b, c, e, g in zip(d, lat, lon, ele, grade, strict=True)
        ],
        "pois": json.loads(pois_file.read_text()) if pois_file.exists() else [],
        "attribution": "© swisstopo",
    }
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / "profile.json").write_text(json.dumps(profile, ensure_ascii=False, separators=(",", ":")))
    return profile


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("slug")
    ap.add_argument("--dem-dir", type=Path, default=Path(__file__).parent / ".cache" / "alti3d")
    ap.add_argument("--out", type=Path, default=ROOT / "dist")
    a = ap.parse_args()
    p = build(a.slug, a.dem_dir, a.out / a.slug)
    print(f"{p['slug']}: {p['distance_m']} m, D+ {p['elevation_gain_m']} m, {len(p['points'])} points")
