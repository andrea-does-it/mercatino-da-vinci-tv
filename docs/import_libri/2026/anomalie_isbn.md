# Report anomalie ISBN — Libri 2026

Trascritti **81 libri distinti** dalle 11 foto. Tutti gli ISBN sono stati validati
(checksum ISBN-13) e confermati su Libraccio (titolo + prezzo + copertina).

## 1. ISBN letti male e corretti (checksum non valido in trascrizione)

Questi 4 avevano un checksum ISBN-13 non valido: cifra/e trascritte male dalla
grafia. Corretti cercando per titolo e riconfermati su Libraccio.

| Trascritto | Corretto | Libro | Note |
|---|---|---|---|
| `9788859194442` | **9788891941442** | Real Focus B1/B1+ (Pearson) | cifre errate |
| `9788808919057` | **9788808914057** | Il nuovo **Amaldi** …blu vol.3 (Zanichelli) | ISBN + titolo: avevo letto "Araldi" |
| `9788800508026` | **9788805080526** | Tria corda vol.1 (SEI) | cifre trasposte |
| `9788800508033` | **9788805080533** | Tria corda vol.2 (SEI) | cifre trasposte |

## 2. Titolo manoscritto diverso dal titolo reale (ISBN comunque corretto)

| ISBN | Titolo trascritto | Titolo reale (Libraccio) | Note |
|---|---|---|---|
| `9788842117445` | "Storia dell'arte vol.1" | **A passo d'arte. Vol. 1** (Nifosì, Laterza) | l'ISBN appartiene alla serie "A passo d'arte" delle prime classi; titolo manoscritto letto male |
| `9781035141654` | "The Yellow Grammar Book **2023**" | The Yellow Grammar Book **2025** (Macmillan) | **risolto**: confermata dall'utente l'edizione 2025; titolo manoscritto aggiornato a 2025 |

## 3. ISBN non trovati su Libraccio

**Nessuno.** Tutti gli 81 ISBN (dopo le correzioni) restituiscono un libro con
prezzo di copertina e copertina scaricabile.

## 4. Note prezzo

- Prezzo di copertina totale dei 81 titoli: **€ 2562,05**.
- Prezzo mercatino = `listino/2 − 1,50` (arrotondato a 2 decimali).
- Unico titolo con prezzo mercatino molto basso: `9788857792668` "Physical education
  and sports in English" — listino € 6,60 → mercatino **€ 1,80**.

## 5. Da verificare con l'utente

- ~~Edizione "The Yellow Grammar Book" 2023 vs 2025~~ → **risolto: edizione 2025 confermata**.
- Alcuni titoli compaiono in più sezioni/anni (es. "A passo d'arte", "Itinerario
  nell'arte", "Homo sum civis sum", "Milleduemilatrenta", "È tempo di filosofia",
  "Storia e storiografia", "Leggere e scrivere il mondo") ma con **volumi/ISBN
  diversi**: sono titoli distinti, correttamente non deduplicati.
