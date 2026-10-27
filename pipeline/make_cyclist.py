"""Builds app/public/models/cyclist.glb: a low-poly cyclist on a road bike, no dependencies.

glTF axes: +Y up, +Z forward, +X the rider's left. Parts are named so the app can recolour materials,
toggle style variants (helmet, jersey pattern, wheels) and animate the pivots (wheels, crank, legs).
Run: python3 pipeline/make_cyclist.py
"""

from __future__ import annotations

import json
import math
import struct
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "app" / "public" / "models" / "cyclist.glb"

# material name -> (default colour, metallic, roughness). Colours are overridden by the player's kit.
MATERIALS = {
    "jersey": ("#0a7d3b", 0.0, 0.9),
    "accent": ("#ffffff", 0.0, 0.9),
    "shorts": ("#222222", 0.0, 0.9),
    "helmet": ("#ffffff", 0.0, 0.3),
    "bike": ("#0a4069", 0.6, 0.3),
    "skin": ("#e0ac82", 0.0, 0.8),
    "dark": ("#1b1b1b", 0.2, 0.7),
    "lens": ("#16222c", 0.6, 0.15),
}

Vec = tuple[float, float, float]


def sub(a: Vec, b: Vec) -> Vec:
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def add(a: Vec, b: Vec) -> Vec:
    return (a[0] + b[0], a[1] + b[1], a[2] + b[2])


def mul(a: Vec, k: float) -> Vec:
    return (a[0] * k, a[1] * k, a[2] * k)


def cross(a: Vec, b: Vec) -> Vec:
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def norm(a: Vec) -> Vec:
    n = math.sqrt(a[0] ** 2 + a[1] ** 2 + a[2] ** 2) or 1.0
    return (a[0] / n, a[1] / n, a[2] / n)


class Geo:
    """Positions, normals and triangle indices."""

    def __init__(self) -> None:
        self.p: list[Vec] = []
        self.n: list[Vec] = []
        self.i: list[int] = []

    def extend(self, other: "Geo") -> None:
        base = len(self.p)
        self.p += other.p
        self.n += other.n
        self.i += [base + k for k in other.i]


def ellipsoid(c: Vec, rx: float, ry: float, rz: float, seg: int = 14, rings: int = 8) -> Geo:
    g = Geo()
    for r in range(rings + 1):
        phi = math.pi * r / rings
        for s in range(seg + 1):
            th = 2 * math.pi * s / seg
            u = (math.sin(phi) * math.cos(th), math.cos(phi), math.sin(phi) * math.sin(th))
            g.p.append((c[0] + rx * u[0], c[1] + ry * u[1], c[2] + rz * u[2]))
            g.n.append(norm((u[0] / rx, u[1] / ry, u[2] / rz)))
    for r in range(rings):
        for s in range(seg):
            a, b = r * (seg + 1) + s, (r + 1) * (seg + 1) + s
            g.i += [a, a + 1, b, a + 1, b + 1, b]
    return g


def tube(p0: Vec, p1: Vec, r: float, seg: int = 8, caps: bool = True) -> Geo:
    g = Geo()
    axis = norm(sub(p1, p0))
    helper = (1.0, 0.0, 0.0) if abs(axis[0]) < 0.9 else (0.0, 1.0, 0.0)
    u = norm(cross(axis, helper))
    v = cross(axis, u)
    for end in (p0, p1):
        for s in range(seg + 1):
            th = 2 * math.pi * s / seg
            d = add(mul(u, math.cos(th)), mul(v, math.sin(th)))
            g.p.append(add(end, mul(d, r)))
            g.n.append(d)
    for s in range(seg):
        a, b = s, seg + 1 + s
        g.i += [a, b, a + 1, a + 1, b, b + 1]
    if caps:
        for end, nrm, flip in ((p0, mul(axis, -1), True), (p1, axis, False)):
            base = len(g.p)
            g.p.append(end)
            g.n.append(nrm)
            for s in range(seg + 1):
                th = 2 * math.pi * s / seg
                g.p.append(add(end, mul(add(mul(u, math.cos(th)), mul(v, math.sin(th))), r)))
                g.n.append(nrm)
            for s in range(seg):
                tri = [base, base + 1 + s, base + 2 + s]
                g.i += tri[::-1] if flip else tri
    return g


def capsule(p0: Vec, p1: Vec, r: float, seg: int = 10) -> Geo:
    g = tube(p0, p1, r, seg, caps=False)
    g.extend(ellipsoid(p0, r, r, r, seg, 6))
    g.extend(ellipsoid(p1, r, r, r, seg, 6))
    return g


def torus(c: Vec, big: float, small: float, seg: int = 36, tseg: int = 8) -> Geo:
    """A ring in the Y/Z plane (a wheel whose axle runs along X)."""
    g = Geo()
    for s in range(seg + 1):
        th = 2 * math.pi * s / seg
        centre = (c[0], c[1] + big * math.cos(th), c[2] + big * math.sin(th))
        radial = (0.0, math.cos(th), math.sin(th))
        for t in range(tseg + 1):
            ph = 2 * math.pi * t / tseg
            d = add(mul(radial, math.cos(ph)), (math.sin(ph), 0.0, 0.0))
            g.p.append(add(centre, mul(d, small)))
            g.n.append(d)
    for s in range(seg):
        for t in range(tseg):
            a, b = s * (tseg + 1) + t, (s + 1) * (tseg + 1) + t
            g.i += [a, b, a + 1, a + 1, b, b + 1]
    return g


def box(c: Vec, size: Vec) -> Geo:
    g = Geo()
    hx, hy, hz = size[0] / 2, size[1] / 2, size[2] / 2
    faces = [
        ((1, 0, 0), [(1, -1, -1), (1, 1, -1), (1, 1, 1), (1, -1, 1)]),
        ((-1, 0, 0), [(-1, -1, 1), (-1, 1, 1), (-1, 1, -1), (-1, -1, -1)]),
        ((0, 1, 0), [(-1, 1, -1), (-1, 1, 1), (1, 1, 1), (1, 1, -1)]),
        ((0, -1, 0), [(-1, -1, 1), (-1, -1, -1), (1, -1, -1), (1, -1, 1)]),
        ((0, 0, 1), [(1, -1, 1), (1, 1, 1), (-1, 1, 1), (-1, -1, 1)]),
        ((0, 0, -1), [(-1, -1, -1), (-1, 1, -1), (1, 1, -1), (1, -1, -1)]),
    ]
    for nrm, corners in faces:
        base = len(g.p)
        for cx, cy, cz in corners:
            g.p.append((c[0] + cx * hx, c[1] + cy * hy, c[2] + cz * hz))
            g.n.append(tuple(float(k) for k in nrm))  # type: ignore[arg-type]
        g.i += [base, base + 1, base + 2, base, base + 2, base + 3]
    return g


Prims = dict[str, Geo]


def prims(**by_material: list[Geo]) -> Prims:
    out: Prims = {}
    for material, geos in by_material.items():
        g = Geo()
        for geo in geos:
            g.extend(geo)
        out[material] = g
    return out


# --- the bike (bottom bracket at the origin of crank/pedals) -------------------------------------
BB = (0.0, 0.30, 0.0)
SEAT = (0.0, 0.80, -0.17)
HEAD_TOP, HEAD_BOT = (0.0, 0.74, 0.37), (0.0, 0.60, 0.40)
REAR, FRONT = (0.0, 0.34, -0.42), (0.0, 0.34, 0.60)
R_WHEEL = 0.34
HIP_Y, HIP_Z, HIP_X = 0.93, -0.15, 0.09
THIGH = SHIN = 0.42

_dt = norm(sub(HEAD_BOT, BB))
_up = norm(cross((1.0, 0.0, 0.0), _dt))  # perpendicular to the down tube, pointing up and back


def _on_down_tube(t: float) -> Vec:
    return add(add(mul(BB, 1 - t), mul(HEAD_BOT, t)), mul(_up, 0.05))


frame = prims(
    bike=[
        tube(BB, SEAT, 0.018), tube(SEAT, HEAD_TOP, 0.016), tube(BB, HEAD_BOT, 0.02),
        tube(BB, REAR, 0.012), tube(SEAT, REAR, 0.01), tube(HEAD_BOT, FRONT, 0.014),
        tube(HEAD_BOT, HEAD_TOP, 0.022), tube(SEAT, (0.0, 0.86, -0.185), 0.011),
    ],
    accent=[capsule(_on_down_tube(0.3), _on_down_tube(0.62), 0.03, 8)],
    dark=[
        tube(_on_down_tube(0.3), _on_down_tube(0.1), 0.012, 6),
        tube((0.18, 0.74, 0.50), (0.17, 0.68, 0.45), 0.01), tube((-0.18, 0.74, 0.50), (-0.17, 0.68, 0.45), 0.01),
        tube((0.17, 0.68, 0.45), (0.21, 0.80, 0.42), 0.01), tube((-0.17, 0.68, 0.45), (-0.21, 0.80, 0.42), 0.01),
        ellipsoid((0.0, 0.875, -0.18), 0.065, 0.02, 0.13), ellipsoid((0.0, 0.872, -0.06), 0.02, 0.016, 0.06, 8, 5), tube((0.0, 0.74, 0.37), (0.0, 0.80, 0.40), 0.012),
        tube((0.21, 0.80, 0.42), (-0.21, 0.80, 0.42), 0.012), tube((0.21, 0.80, 0.42), (0.18, 0.74, 0.50), 0.01),
        tube((-0.21, 0.80, 0.42), (-0.18, 0.74, 0.50), 0.01),
    ],
)
tyre = prims(dark=[torus((0.0, 0.0, 0.0), R_WHEEL - 0.02, 0.022, 36, 8)])
spoked = prims(
    bike=[torus((0.0, 0.0, 0.0), R_WHEEL - 0.045, 0.012, 36, 6), tube((0.04, 0, 0), (-0.04, 0, 0), 0.02)],
    dark=[tube((0, 0, 0), (0, math.cos(a) * (R_WHEEL - 0.05), math.sin(a) * (R_WHEEL - 0.05)), 0.003, 4, False)
          for a in [2 * math.pi * k / 20 for k in range(20)]],
)
disc = prims(bike=[tube((0.012, 0, 0), (-0.012, 0, 0), R_WHEEL - 0.04, 36)])
crank = prims(dark=[tube((0.07, 0, 0), (0.07, 0.17, 0), 0.012), tube((-0.07, 0, 0), (-0.07, -0.17, 0), 0.012),
                    tube((0.07, 0, 0), (-0.07, 0, 0), 0.02), ellipsoid((0.0, 0.0, 0.0), 0.05, 0.05, 0.05, 10, 6),
                    tube((0.05, 0, 0), (0.04, 0, 0), 0.085, 20)])
pedal = prims(dark=[box((0, 0, 0), (0.1, 0.02, 0.07))])

# --- the rider -------------------------------------------------------------------------------------
TORSO0, TORSO1 = (0.0, 0.98, -0.12), (0.0, 1.17, 0.14)
shorts = prims(shorts=[ellipsoid((0.0, HIP_Y, HIP_Z), 0.16, 0.11, 0.17)])
torso = prims(
    jersey=[capsule(TORSO0, TORSO1, 0.14, 14), ellipsoid((0.0, 1.155, 0.15), 0.2, 0.075, 0.085, 14, 6)],
    skin=[tube((0.0, 1.19, 0.18), (0.0, 1.27, 0.23), 0.04, 8)],
)


def torso_ring(t: float, width: float) -> Geo:
    c = add(mul(TORSO0, 1 - t), mul(TORSO1, t))
    axis = norm(sub(TORSO1, TORSO0))
    return tube(sub(c, mul(axis, width / 2)), add(c, mul(axis, width / 2)), 0.145, 14, False)


stripes = prims(accent=[torso_ring(t, 0.025) for t in (0.3, 0.5, 0.7)])
band = prims(accent=[torso_ring(0.55, 0.11)])
arms = prims(
    jersey=[capsule((s * 0.19, 1.15, 0.13), (s * 0.23, 0.99, 0.21), 0.047, 12) for s in (1, -1)],
    skin=[capsule((s * 0.23, 0.99, 0.21), (s * 0.205, 0.84, 0.42), 0.036, 10) for s in (1, -1)],
    dark=[ellipsoid((s * 0.2, 0.82, 0.435), 0.04, 0.035, 0.055, 10, 6) for s in (1, -1)],
)
head = prims(
    skin=[ellipsoid((0.0, 1.30, 0.24), 0.085, 0.10, 0.10)],
    lens=[ellipsoid((0.0, 1.30, 0.325), 0.082, 0.026, 0.02, 12, 5)],
)
helmet_road = prims(helmet=[ellipsoid((0.0, 1.335, 0.225), 0.105, 0.075, 0.135)])
helmet_aero = prims(helmet=[ellipsoid((0.0, 1.325, 0.17), 0.095, 0.075, 0.23)])
helmet_cap = prims(helmet=[ellipsoid((0.0, 1.365, 0.235), 0.10, 0.04, 0.10), box((0.0, 1.34, 0.325), (0.1, 0.012, 0.07))])
thigh = prims(
    shorts=[capsule((0, 0, 0), (0, -0.30, 0), 0.07, 12), ellipsoid((0.0, -0.14, 0.012), 0.082, 0.17, 0.085, 12, 6)],
    skin=[capsule((0, -0.30, 0), (0, -THIGH, 0), 0.054, 10)],
)
shin = prims(
    skin=[capsule((0, 0, 0), (0, -SHIN, 0), 0.045, 10), ellipsoid((0.0, -0.13, -0.022), 0.056, 0.11, 0.06, 10, 6)],
    accent=[tube((0, -0.33, 0), (0, -0.39, 0), 0.05, 10, False)],
    dark=[ellipsoid((0.0, -SHIN - 0.02, 0.05), 0.048, 0.032, 0.11), ellipsoid((0.0, -SHIN - 0.045, 0.055), 0.045, 0.01, 0.105, 10, 4)],
)


# --- glTF assembly ---------------------------------------------------------------------------------
class Builder:
    def __init__(self) -> None:
        self.nodes: list[dict] = []
        self.meshes: list[dict] = []
        self.accessors: list[dict] = []
        self.views: list[dict] = []
        self.bin = bytearray()
        self.mat_index = {name: i for i, name in enumerate(MATERIALS)}

    def _view(self, data: bytes, target: int) -> int:
        while len(self.bin) % 4:
            self.bin.append(0)
        self.views.append({"buffer": 0, "byteOffset": len(self.bin), "byteLength": len(data), "target": target})
        self.bin += data
        return len(self.views) - 1

    def _accessor(self, view: int, ctype: int, count: int, kind: str, **extra: list[float]) -> int:
        self.accessors.append({"bufferView": view, "componentType": ctype, "count": count, "type": kind, **extra})
        return len(self.accessors) - 1

    def mesh(self, by_material: Prims) -> int:
        primitives = []
        for material, g in by_material.items():
            flat = [c for v in g.p for c in v]
            pos = self._accessor(
                self._view(struct.pack(f"<{len(flat)}f", *flat), 34962), 5126, len(g.p), "VEC3",
                min=[min(v[k] for v in g.p) for k in range(3)], max=[max(v[k] for v in g.p) for k in range(3)],
            )
            nflat = [c for v in g.n for c in v]
            nor = self._accessor(self._view(struct.pack(f"<{len(nflat)}f", *nflat), 34962), 5126, len(g.n), "VEC3")
            idx = self._accessor(self._view(struct.pack(f"<{len(g.i)}H", *g.i), 34963), 5123, len(g.i), "SCALAR")
            primitives.append({"attributes": {"POSITION": pos, "NORMAL": nor}, "indices": idx, "material": self.mat_index[material]})
        self.meshes.append({"primitives": primitives})
        return len(self.meshes) - 1

    def node(self, name: str, geometry: Prims | None = None, at: Vec | None = None, children: list[int] | None = None) -> int:
        n: dict = {"name": name}
        if geometry:
            n["mesh"] = self.mesh(geometry)
        if at:
            n["translation"] = list(at)
        if children:
            n["children"] = children
        self.nodes.append(n)
        return len(self.nodes) - 1

    def glb(self, root: int) -> bytes:
        def rgba(h: str) -> list[float]:
            return [int(h[i : i + 2], 16) / 255 for i in (1, 3, 5)] + [1.0]

        doc = {
            "asset": {"version": "2.0", "generator": "vaud ride pipeline/make_cyclist.py"},
            "scene": 0,
            "scenes": [{"nodes": [root]}],
            "nodes": self.nodes,
            "meshes": self.meshes,
            "materials": [
                {"name": name, "pbrMetallicRoughness": {"baseColorFactor": rgba(c), "metallicFactor": m, "roughnessFactor": r}}
                for name, (c, m, r) in MATERIALS.items()
            ],
            "accessors": self.accessors,
            "bufferViews": self.views,
            "buffers": [{"byteLength": len(self.bin)}],
        }
        js = json.dumps(doc, separators=(",", ":")).encode()
        js += b" " * (-len(js) % 4)
        data = bytes(self.bin) + b"\0" * (-len(self.bin) % 4)
        body = struct.pack("<I4s", len(js), b"JSON") + js
        body += struct.pack("<I4s", len(data), b"BIN\0") + data
        return struct.pack("<III", 0x46546C67, 2, 12 + len(body)) + body


def build() -> bytes:
    b = Builder()
    wheel = lambda name, at: b.node(name, tyre, at, [b.node(f"{name}_spoked", spoked), b.node(f"{name}_disc", disc)])  # noqa: E731
    shin_l, shin_r = b.node("shin_left", shin, (0.0, -THIGH, 0.0)), b.node("shin_right", shin, (0.0, -THIGH, 0.0))
    root = b.node(
        "cyclist",
        children=[
            b.node("frame", frame),
            wheel("wheel_front", FRONT),
            wheel("wheel_rear", REAR),
            b.node("crank", crank, BB),
            b.node("pedal_left", pedal, BB),
            b.node("pedal_right", pedal, BB),
            b.node("hips", shorts),
            b.node("torso", torso, children=[b.node("jersey_stripes", stripes), b.node("jersey_band", band)]),
            b.node("arms", arms),
            b.node("head", head, children=[b.node("helmet_road", helmet_road), b.node("helmet_aero", helmet_aero), b.node("helmet_cap", helmet_cap)]),
            b.node("thigh_left", thigh, (HIP_X, HIP_Y, HIP_Z), [shin_l]),
            b.node("thigh_right", thigh, (-HIP_X, HIP_Y, HIP_Z), [shin_r]),
        ],
    )
    return b.glb(root)


if __name__ == "__main__":
    OUT.parent.mkdir(parents=True, exist_ok=True)
    data = build()
    OUT.write_bytes(data)
    print(f"{OUT.relative_to(OUT.parents[3])}: {len(data) / 1024:.0f} KB")
