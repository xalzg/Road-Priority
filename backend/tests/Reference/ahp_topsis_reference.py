"""
Referensi independen AHP-TOPSIS (hanya library standar Python, tanpa numpy).

Tujuan: menghasilkan fixture tanpa pembulatan untuk tes backend (Master PRD, PRD 05)
dengan metode yang BERBEDA dari implementasi PHP:
  - PHP   : power iteration pada matriks perbandingan.
  - Python: akar terbesar polinomial karakteristik det(A - lambda*I) = 0 (pemindaian +
            bisection), lalu vektor eigen dari sistem linear (eliminasi Gauss). Power
            iteration hanya dipakai sebagai pemeriksaan silang di dalam skrip ini.

Jalankan:  python ahp_topsis_reference.py > ../Fixtures/reference.json
Data pada skrip ini ILUSTRATIF (Lampiran C), bukan data penelitian.
"""
import json
import math

RI = {1: 0.0, 2: 0.0, 3: 0.58, 4: 0.90, 5: 1.12, 6: 1.24, 7: 1.32, 8: 1.41, 9: 1.45, 10: 1.49}


def det(m):
    """Determinan lewat eliminasi Gauss dengan pivot parsial."""
    a = [row[:] for row in m]
    n = len(a)
    d = 1.0
    for c in range(n):
        p = max(range(c, n), key=lambda r: abs(a[r][c]))
        if abs(a[p][c]) < 1e-300:
            return 0.0
        if p != c:
            a[c], a[p] = a[p], a[c]
            d = -d
        d *= a[c][c]
        for r in range(c + 1, n):
            f = a[r][c] / a[c][c]
            for k in range(c, n):
                a[r][k] -= f * a[c][k]
    return d


def char_poly(a, lam):
    n = len(a)
    return det([[a[i][j] - (lam if i == j else 0.0) for j in range(n)] for i in range(n)])


def dominant_eigenvalue(a):
    n = len(a)
    hi = max(sum(row) for row in a) + 1e-9  # batas atas jari-jari spektral
    sign_hi = math.copysign(1.0, char_poly(a, hi))
    step = hi / 200000.0
    lam = hi
    prev = hi
    while lam > 0:
        lam -= step
        if math.copysign(1.0, char_poly(a, lam)) != sign_hi:
            lo, up = lam, prev
            for _ in range(200):
                mid = (lo + up) / 2.0
                if math.copysign(1.0, char_poly(a, mid)) == sign_hi:
                    up = mid
                else:
                    lo = mid
            return (lo + up) / 2.0
        prev = lam
    raise RuntimeError("akar dominan tidak ditemukan")


def solve(a, b):
    n = len(a)
    m = [a[i][:] + [b[i]] for i in range(n)]
    for c in range(n):
        p = max(range(c, n), key=lambda r: abs(m[r][c]))
        m[c], m[p] = m[p], m[c]
        for r in range(c + 1, n):
            f = m[r][c] / m[c][c]
            for k in range(c, n + 1):
                m[r][k] -= f * m[c][k]
    x = [0.0] * n
    for i in range(n - 1, -1, -1):
        x[i] = (m[i][n] - sum(m[i][k] * x[k] for k in range(i + 1, n))) / m[i][i]
    return x


def eigenvector(a, lam):
    """Selesaikan (A - lam*I) w = 0 dengan baris terakhir diganti sum(w) = 1."""
    n = len(a)
    mat = [[a[i][j] - (lam if i == j else 0.0) for j in range(n)] for i in range(n)]
    mat[n - 1] = [1.0] * n
    rhs = [0.0] * (n - 1) + [1.0]
    return solve(mat, rhs)


def power_iteration(a, tol=1e-15, iters=100000):
    n = len(a)
    w = [1.0 / n] * n
    for _ in range(iters):
        y = [sum(a[i][j] * w[j] for j in range(n)) for i in range(n)]
        s = sum(y)
        nw = [v / s for v in y]
        if max(abs(nw[i] - w[i]) for i in range(n)) < tol:
            return nw
        w = nw
    return w


def ahp(a):
    n = len(a)
    if n == 1:
        return {"n": 1, "weights": [1.0], "lambda_max": 1.0, "ci": 0.0, "cr": 0.0}
    lam = dominant_eigenvalue(a)
    w = eigenvector(a, lam)
    # pemeriksaan silang dengan power iteration
    pw = power_iteration(a)
    assert max(abs(w[i] - pw[i]) for i in range(n)) < 1e-9, "eigenvector tidak cocok dengan power iteration"
    ci = (lam - n) / (n - 1)
    cr = 0.0 if n <= 2 else ci / RI[n]
    return {"n": n, "weights": w, "lambda_max": lam, "ci": ci, "cr": cr}


def geometric_mean_matrix(mats):
    n = len(mats[0])
    k = len(mats)
    out = [[1.0] * n for _ in range(n)]
    for i in range(n):
        for j in range(i + 1, n):
            g = 1.0
            for m in mats:
                g *= m[i][j]
            g = g ** (1.0 / k)
            out[i][j] = g
            out[j][i] = 1.0 / g
    return out


def topsis(alts, types, weights):
    codes = sorted(alts)
    m, n = len(codes), len(types)
    x = [alts[c] for c in codes]
    den = [math.sqrt(sum(x[i][j] ** 2 for i in range(m))) for j in range(n)]
    r = [[x[i][j] / den[j] for j in range(n)] for i in range(m)]
    v = [[weights[j] * r[i][j] for j in range(n)] for i in range(m)]
    ap, am = [], []
    for j in range(n):
        col = [v[i][j] for i in range(m)]
        if types[j] == "benefit":
            ap.append(max(col)); am.append(min(col))
        else:
            ap.append(min(col)); am.append(max(col))
    dp = [math.sqrt(sum((v[i][j] - ap[j]) ** 2 for j in range(n))) for i in range(m)]
    dm = [math.sqrt(sum((v[i][j] - am[j]) ** 2 for j in range(n))) for i in range(m)]
    c = [dm[i] / (dp[i] + dm[i]) for i in range(m)]
    order = sorted(range(m), key=lambda i: (-c[i], codes[i]))
    rank = {codes[i]: pos + 1 for pos, i in enumerate(order)}
    return {"codes": codes, "normalized": r, "weighted": v, "a_plus": ap, "a_minus": am,
            "d_plus": dp, "d_minus": dm, "closeness": c, "rank": rank}


def spearman(ra, rb):
    keys = sorted(ra)
    n = len(keys)
    d2 = sum((ra[k] - rb[k]) ** 2 for k in keys)
    return 1 - 6 * d2 / (n * (n * n - 1))


def main():
    out = {"note": "Fixture ilustratif dari referensi independen Python; BUKAN data penelitian."}

    # Lampiran C
    A = [[1, 3, 5], [1 / 3, 1, 2], [1 / 5, 1 / 2, 1]]
    a = ahp(A)
    types = ["benefit", "benefit", "cost"]
    alts = {"A1": [4, 3, 150], "A2": [5, 2, 600], "A3": [3, 5, 300], "A4": [2, 4, 100]}
    t = topsis(alts, types, a["weights"])
    eq = topsis(alts, types, [1 / 3] * 3)
    out["lampiran_c"] = {
        "matrix": A, "ahp": a, "types": types, "alternatives": alts, "topsis": t,
        "equal_weights": eq, "spearman_equal_vs_base": spearman(t["rank"], eq["rank"]),
    }

    # Matriks inkonsisten 4x4 (siklus preferensi A>B>C>D>A) -> CR > 0,10
    B = [[1, 9, 1 / 9, 9], [1 / 9, 1, 9, 1 / 9], [9, 1 / 9, 1, 9], [1 / 9, 9, 1 / 9, 1]]
    out["inconsistent_4x4"] = {"matrix": B, "ahp": ahp(B)}

    # Dua matriks 3x3 di dekat ambang CR = 0,10 (satu di bawah, satu di atas)
    below = [[1, 3, 4], [1 / 3, 1, 1 / 2], [1 / 4, 2, 1]]
    above = [[1, 5, 9], [1 / 5, 1, 5], [1 / 9, 1 / 5, 1]]
    out["borderline_below"] = {"matrix": below, "ahp": ahp(below)}
    out["borderline_above"] = {"matrix": above, "ahp": ahp(above)}

    # Matriks konsisten sempurna a_ij = w_i / w_j (n = 5)
    w = [0.40, 0.25, 0.15, 0.12, 0.08]
    C = [[w[i] / w[j] for j in range(5)] for i in range(5)]
    out["consistent_5x5"] = {"weights": w, "matrix": C, "ahp": ahp(C)}

    # Agregasi geometric mean dua pakar (n = 3)
    P1 = [[1, 3, 5], [1 / 3, 1, 2], [1 / 5, 1 / 2, 1]]
    P2 = [[1, 5, 7], [1 / 5, 1, 3], [1 / 7, 1 / 3, 1]]
    G = geometric_mean_matrix([P1, P2])
    out["group_gm"] = {"experts": [P1, P2], "aggregated": G, "ahp": ahp(G)}

    # Kriteria benefit/cost dengan 3 kriteria dan 5 alternatif (bobot tetap)
    alts2 = {"S-01": [10, 200, 5], "S-02": [30, 100, 2], "S-03": [20, 400, 9], "S-04": [25, 150, 1], "S-05": [5, 300, 4]}
    w2 = [0.5, 0.3, 0.2]
    out["topsis_5x3"] = {"types": ["benefit", "cost", "benefit"], "weights": w2, "alternatives": alts2,
                         "topsis": topsis(alts2, ["benefit", "cost", "benefit"], w2)}

    print(json.dumps(out, indent=2))


if __name__ == "__main__":
    main()
