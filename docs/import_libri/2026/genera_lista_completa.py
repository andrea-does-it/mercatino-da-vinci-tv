# -*- coding: utf-8 -*-
"""Genera la lista completa dei libri 2026-27 dall'elenco ufficiale AIE,
aggiunge le edizioni compatibili segnalate dai dipartimenti, calcola il prezzo
mercatino, confronta con la prima trascrizione (libri_2026.csv) e produce file
CSV in formato standard: SEPARATORE VIRGOLA, tutti i campi racchiusi tra DOPPI APICI.
Riproducibile/idempotente: py genera_lista_completa.py"""
import xlrd, csv, re, urllib.request, os

AIE = 'TVPS01000X_Elenco_adozioni_per_MATERIA-AIE_2026-27.xls'

# --- pulizia caratteri (cp1252 / accenti errati) ---
CTRL = {'\x91': "'", '\x92': "'", '\x93': '"', '\x94': '"',
        '\x95': '-', '\x96': '-', '\x97': '-', '\x85': '...'}
def clean(s):
    s = str(s)
    for k, v in CTRL.items():
        s = s.replace(k, v)
    s = s.replace('\xc1', '\xc0')   # Á (acuto, errato) -> À (grave)
    s = s.replace("ETA'", 'ET\xc0') # ETA' -> ETÀ
    s = s.replace("C'E'", "C'\xc8") # C'E' -> C'È
    return s.strip()

def norm(x):
    s = str(x).strip()
    if re.match(r'^\d+\.0$', s):
        s = s[:-2]
    return re.sub(r'[^0-9X]', '', s.upper())

def fnum(x):
    try:
        return round(float(str(x).strip()), 2)
    except Exception:
        return None

def read_any(path):
    with open(path, encoding='utf-8') as f:
        head = f.readline()
    delim = ';' if head.count(';') > head.count(',') else ','
    with open(path, encoding='utf-8') as f:
        return list(csv.DictReader(f, delimiter=delim))

def write_std(path, fieldnames, rows):
    """CSV standard: virgola + tutti i campi tra doppi apici."""
    with open(path, 'w', encoding='utf-8', newline='') as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, delimiter=',',
                           quotechar='"', quoting=csv.QUOTE_ALL)
        w.writeheader()
        w.writerows(rows)

# ---- parse AIE ----
bk = xlrd.open_workbook(AIE, ignore_workbook_corruption=True)
sh = bk.sheet_by_index(0)

materia = {}
for r in range(sh.nrows - 1):
    if str(sh.cell_value(r, 0)).strip() == 'isbn' and str(sh.cell_value(r, 1)).strip() == 'Materie':
        isbn = norm(sh.cell_value(r + 1, 0))
        if len(isbn) >= 10 and isbn not in materia:
            materia[isbn] = clean(sh.cell_value(r + 1, 1))

books = {}
for r in range(3, sh.nrows):
    c0 = str(sh.cell_value(r, 0)).strip()
    if not re.match(r'^\d+(\.0)?$', c0):
        continue
    isbn = norm(sh.cell_value(r, 1))
    if len(isbn) < 10:
        continue
    cls = str(sh.cell_value(r, 13)).strip()
    if isbn not in books:
        vol = str(sh.cell_value(r, 3)).strip()
        if re.match(r'^\d+\.0$', vol):
            vol = vol[:-2]
        books[isbn] = {
            'titolo': clean(sh.cell_value(r, 2)), 'volume': vol,
            'autori': clean(sh.cell_value(r, 4)), 'editore': clean(sh.cell_value(r, 5)),
            'materia': materia.get(isbn, ''), 'prezzo': fnum(sh.cell_value(r, 7)),
            'nuova': str(sh.cell_value(r, 8)).strip(), 'da_acq': str(sh.cell_value(r, 10)).strip(),
            'consigliato': str(sh.cell_value(r, 11)).strip(), 'religione': str(sh.cell_value(r, 12)).strip(),
            'fonte': 'AIE', 'classi': set(),
        }
    if cls:
        books[isbn]['classi'].add(cls)

# edizioni compatibili (file .eml - Dip. Italiano, epica)
narr = books.get(norm('9788824762700'))
classi_epica = narr['classi'] if narr else set()
mat_epica = narr['materia'] if narr else 'ITALIANO EPICA'
for isbn, tit, aut, ed, pr in [
    ('9788824744805', 'Narrami o musa (Il nuovo) - seconda edizione', 'Daniela Ciocca, Tina Ferri', 'Mondadori Scuola', 33.10),
    ('9788824731874', 'Narrami o musa (Il nuovo) - prima edizione 2014', 'Daniela Ciocca, Tina Ferri', 'Mondadori Scuola', 28.85),
]:
    isbn = norm(isbn)
    if isbn not in books:
        books[isbn] = {'titolo': tit, 'volume': '0', 'autori': aut, 'editore': ed,
                       'materia': mat_epica, 'prezzo': pr, 'nuova': 'NO', 'da_acq': 'NO',
                       'consigliato': 'NO', 'religione': 'NO',
                       'fonte': 'compatibile (Dip. Italiano)', 'classi': set(classi_epica)}

my = set(norm(r['isbn']) for r in read_any('libri_2026.csv'))

cols = ['isbn','titolo','volume','autori','editore','materia','prezzo_listino','prezzo_mercatino',
        'classi','fonte','nuova_adozione','da_acquistare','consigliato','religione','presente_in_v1']
out = []
for isbn in sorted(books, key=lambda i: (books[i]['materia'], books[i]['titolo'])):
    b = books[isbn]; pl = b['prezzo']
    pm = round(pl/2 - 1.50, 2) if pl is not None else ''
    out.append({'isbn': isbn, 'titolo': b['titolo'], 'volume': b['volume'], 'autori': b['autori'],
                'editore': b['editore'], 'materia': b['materia'],
                'prezzo_listino': ('%.2f' % pl) if pl is not None else '',
                'prezzo_mercatino': ('%.2f' % pm) if pm != '' else '',
                'classi': ' '.join(sorted(b['classi'])), 'fonte': b['fonte'],
                'nuova_adozione': b['nuova'], 'da_acquistare': b['da_acq'],
                'consigliato': b['consigliato'], 'religione': b['religione'],
                'presente_in_v1': 'SI' if isbn in my else 'NO'})
write_std('libri_2026_completo.csv', cols, out)

# riformatta anche le altre due CSV in formato standard (virgola + apici), pulendo i caratteri
for path in ('libri_2026.csv', 'libri_2026_arricchito.csv'):
    rows = read_any(path)
    fn = list(rows[0].keys()) if rows else []
    for row in rows:
        for k in row:
            if isinstance(row[k], str):
                row[k] = clean(row[k])
    write_std(path, fn, rows)

# copertine mancanti
os.makedirs('covers', exist_ok=True)
miss = []
for isbn in (set(books) - my):
    dest = f'covers/{isbn}.jpg'
    if os.path.exists(dest):
        continue
    try:
        req = urllib.request.Request(f'https://www.libraccio.it/images/{isbn}_0_500_0_75.jpg',
                                     headers={'User-Agent': 'Mozilla/5.0'})
        d = urllib.request.urlopen(req, timeout=20).read()
        (open(dest, 'wb').write(d) if len(d) >= 2000 else miss.append(isbn))
    except Exception:
        miss.append(isbn)

print('CSV completo:', len(out), 'libri | nuovi vs v1:', len(set(books) - my), '| copertine mancanti:', miss)
print('Formato: separatore VIRGOLA, campi tra DOPPI APICI (QUOTE_ALL).')
