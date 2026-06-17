# -*- coding: utf-8 -*-
"""Genera la lista completa dei libri 2026-27 a partire dall'elenco ufficiale AIE,
aggiunge le edizioni compatibili segnalate dai dipartimenti, calcola il prezzo
mercatino e confronta con la prima trascrizione (libri_2026.csv).
Riproducibile: py genera_lista_completa.py"""
import xlrd, csv, re, urllib.request, os

AIE = 'TVPS01000X_Elenco_adozioni_per_MATERIA-AIE_2026-27.xls'

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

bk = xlrd.open_workbook(AIE, ignore_workbook_corruption=True)
sh = bk.sheet_by_index(0)

# 1) isbn -> materia, leggendo le righe-intestazione (riga 'isbn'/'Materie' seguita da isbn+materia)
materia = {}
for r in range(sh.nrows - 1):
    if str(sh.cell_value(r, 0)).strip() == 'isbn' and str(sh.cell_value(r, 1)).strip() == 'Materie':
        isbn = norm(sh.cell_value(r + 1, 0))
        mat = str(sh.cell_value(r + 1, 1)).strip()
        if len(isbn) >= 10 and isbn not in materia:
            materia[isbn] = mat

# 2) righe di dettaglio -> libri (aggrega classi)
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
            'titolo': str(sh.cell_value(r, 2)).strip(),
            'volume': vol,
            'autori': str(sh.cell_value(r, 4)).strip(),
            'editore': str(sh.cell_value(r, 5)).strip(),
            'materia': materia.get(isbn, ''),
            'prezzo': fnum(sh.cell_value(r, 7)),
            'nuova': str(sh.cell_value(r, 8)).strip(),
            'da_acq': str(sh.cell_value(r, 10)).strip(),
            'consigliato': str(sh.cell_value(r, 11)).strip(),
            'religione': str(sh.cell_value(r, 12)).strip(),
            'fonte': 'AIE',
            'classi': set(),
        }
    if cls:
        books[isbn]['classi'].add(cls)

# 3) edizioni compatibili segnalate dai dipartimenti (non presenti nell'export AIE)
#    Epica - Dipartimento di Italiano (file .eml)
narr = books.get(norm('9788824762700'))
classi_epica = narr['classi'] if narr else set()
mat_epica = narr['materia'] if narr else 'EPICA'
compat = [
    ('9788824744805', 'Narrami o musa (Il nuovo) - seconda edizione', 'Daniela Ciocca, Tina Ferri', 'Mondadori Scuola', 33.10),
    ('9788824731874', 'Narrami o musa (Il nuovo) - prima edizione 2014', 'Daniela Ciocca, Tina Ferri', 'Mondadori Scuola', 28.85),
]
for isbn, tit, aut, ed, pr in compat:
    isbn = norm(isbn)
    if isbn in books:
        continue
    books[isbn] = {
        'titolo': tit, 'volume': '0', 'autori': aut, 'editore': ed, 'materia': mat_epica,
        'prezzo': pr, 'nuova': 'NO', 'da_acq': 'NO', 'consigliato': 'NO', 'religione': 'NO',
        'fonte': 'compatibile (Dip. Italiano)', 'classi': set(classi_epica),
    }

# v1
mine = {}
for row in csv.DictReader(open('libri_2026.csv', encoding='utf-8'), delimiter=';'):
    mine[norm(row['isbn'])] = row
my = set(mine)

# 4) scrivi CSV completo
cols = ['isbn','titolo','volume','autori','editore','materia','prezzo_listino','prezzo_mercatino',
        'classi','fonte','nuova_adozione','da_acquistare','consigliato','religione','presente_in_v1']
with open('libri_2026_completo.csv', 'w', encoding='utf-8', newline='') as f:
    w = csv.DictWriter(f, fieldnames=cols, delimiter=';')
    w.writeheader()
    for isbn in sorted(books, key=lambda i: (books[i]['materia'], books[i]['titolo'])):
        b = books[isbn]; pl = b['prezzo']
        pm = round(pl/2 - 1.50, 2) if pl is not None else ''
        w.writerow({
            'isbn': isbn, 'titolo': b['titolo'], 'volume': b['volume'], 'autori': b['autori'],
            'editore': b['editore'], 'materia': b['materia'],
            'prezzo_listino': ('%.2f' % pl) if pl is not None else '',
            'prezzo_mercatino': ('%.2f' % pm) if pm != '' else '',
            'classi': ' '.join(sorted(b['classi'])), 'fonte': b['fonte'],
            'nuova_adozione': b['nuova'], 'da_acquistare': b['da_acq'],
            'consigliato': b['consigliato'], 'religione': b['religione'],
            'presente_in_v1': 'SI' if isbn in my else 'NO',
        })

new = set(books) - my
# 5) scarica copertine mancanti (nuovi)
os.makedirs('covers', exist_ok=True)
miss = []
for isbn in new:
    dest = f'covers/{isbn}.jpg'
    if os.path.exists(dest):
        continue
    try:
        req = urllib.request.Request(f'https://www.libraccio.it/images/{isbn}_0_500_0_75.jpg',
                                     headers={'User-Agent': 'Mozilla/5.0'})
        d = urllib.request.urlopen(req, timeout=20).read()
        if len(d) < 2000:
            miss.append(isbn)
        else:
            open(dest, 'wb').write(d)
    except Exception:
        miss.append(isbn)

print('Totale libri nel CSV completo:', len(books))
print('  di cui da AIE:', sum(1 for b in books.values() if b['fonte'] == 'AIE'))
print('  di cui compatibili (Dip.):', sum(1 for b in books.values() if b['fonte'].startswith('compatibile')))
print('In comune con v1:', len(set(books) & my), '| nuovi rispetto a v1:', len(new))
print('Copertine mancanti:', miss)
