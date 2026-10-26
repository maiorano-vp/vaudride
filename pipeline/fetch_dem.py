"""Download the 2 m swissALTI3D tiles (open data, © swisstopo) covering courses/<slug>/route.gpx."""

import json
import sys
import urllib.request
from pathlib import Path

import gpxpy
from pyproj import Transformer
from shapely.geometry import LineString, box

STAC = "https://data.geo.admin.ch/api/stac/v0.9/collections/ch.swisstopo.swissalti3d/items"
ROOT = Path(__file__).resolve().parent.parent


def tiles_for(slug: str) -> dict[str, str]:
    gpx = gpxpy.parse((ROOT / "courses" / slug / "route.gpx").read_text())
    pts = [p for t in gpx.tracks for s in t.segments for p in s.points]
    lon, lat = [p.longitude for p in pts], [p.latitude for p in pts]
    pad = 0.002
    url: str | None
    url = f"{STAC}?bbox={min(lon)-pad},{min(lat)-pad},{max(lon)+pad},{max(lat)+pad}&limit=100"
    latest: dict[str, tuple[str, str]] = {}  # tile coords -> (year, href)
    while url:
        page = json.load(urllib.request.urlopen(url))
        for f in page["features"]:
            _, year, coords = f["id"].split("_")
            href = next(a["href"] for a in f["assets"].values() if a.get("eo:gsd") == 2.0 and a["href"].endswith(".tif"))
            if coords not in latest or year > latest[coords][0]:
                latest[coords] = (year, href)
        url = next((link["href"] for link in page.get("links", []) if link["rel"] == "next"), None)
    # bbox returns every tile in the rectangle; keep only the 1 km tiles the route (+50 m) crosses
    xs, ys = Transformer.from_crs(4326, 2056, always_xy=True).transform(lon, lat)
    route = LineString(zip(xs, ys, strict=True)).buffer(50)
    def hit(coords: str) -> bool:
        e, n = (int(v) * 1000 for v in coords.split("-"))
        return bool(route.intersects(box(e, n, e + 1000, n + 1000)))
    return {c: h for c, (_, h) in latest.items() if hit(c)}


if __name__ == "__main__":
    out = Path(__file__).parent / ".cache" / "alti3d"
    out.mkdir(parents=True, exist_ok=True)
    tiles = tiles_for(sys.argv[1])
    print(f"{len(tiles)} tiles")
    if "--list" in sys.argv:
        print("\n".join(tiles.values()))
    else:
        for href in tiles.values():
            dest = out / href.rsplit("/", 1)[1]
            if not dest.exists():
                urllib.request.urlretrieve(href, dest)
