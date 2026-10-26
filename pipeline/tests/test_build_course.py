import json
import sys
from pathlib import Path

import numpy as np
import rasterio
from rasterio.transform import from_origin

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
import build_course as bc

E0, N0 = 2_510_000, 1_160_000  # top-left of synthetic 2 m DEM, 4 km x 4 km
SLOPE = 0.08


def make_dem(folder: Path) -> None:
    n = 2000
    rows = np.arange(n)[:, None] * 2.0  # distance south of top edge
    arr = np.repeat(1000 + SLOPE * (4000 - rows), n, axis=1)  # rises northwards
    with rasterio.open(folder / "t.tif", "w", driver="GTiff", height=n, width=n, count=1,
                       dtype="float32", crs="EPSG:2056", transform=from_origin(E0, N0, 2, 2)) as ds:
        ds.write(arr.astype("float32"), 1)


def test_build_8_percent_climb(tmp_path, monkeypatch):
    dem = tmp_path / "dem"
    dem.mkdir()
    make_dem(dem)
    root = tmp_path / "root"
    (root / "courses" / "t").mkdir(parents=True)
    xs = [E0 + 2000] * 2
    ys = [N0 - 3500, N0 - 1500]  # 2000 m heading north
    lon, lat = bc.to_wgs84.transform(xs, ys)
    gpx = "".join(f'<trkpt lat="{b}" lon="{a}"/>' for a, b in zip(lon, lat))
    (root / "courses" / "t" / "route.gpx").write_text(
        f'<gpx version="1.1" creator="t"><trk><trkseg>{gpx}</trkseg></trk></gpx>')
    (root / "courses" / "t" / "overrides.yaml").write_text("name: Test\n")
    monkeypatch.setattr(bc, "ROOT", root)

    p = bc.build("t", dem, tmp_path / "out")

    assert abs(p["distance_m"] - 2000) / 2000 < 0.02
    assert abs(p["elevation_gain_m"] - 160) / 160 < 0.02
    assert all(abs(pt["grade"] - 8) < 0.5 for pt in p["points"][12:-12])
    assert json.loads((tmp_path / "out" / "profile.json").read_text())["attribution"] == "© swisstopo"


def test_grade_clamped():
    d = np.arange(0, 100, 5.0)
    ele = d * 0.5  # 50 %
    g = np.clip(np.gradient(ele, d) * 100, bc.GRADE_MIN, bc.GRADE_MAX)
    assert g.max() == bc.GRADE_MAX


def test_overrides_interpolate():
    d = np.arange(0, 100, 5.0)
    ele = np.where((d > 40) & (d < 60), 999.0, d)
    out = bc.apply_overrides(d, ele, [{"from_d": 40, "to_d": 60}])
    assert np.allclose(out, d)
