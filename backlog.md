# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Rebotling-fokus (ägarens prioritet)

- [x] **Skiftrapport per operatör** — filtrerbar skiftrapport per specifik operatör med trendgraf (0a96daa)
- [x] **IBC-kvalitets deep-dive** — bryt ner ej-godkända per avvisningsorsak (0a96daa)
- [ ] **Annotationer i grafer** — markera ut driftstopp, helgdagar i tidslinjen på statistiksidan
- [ ] **Produktionsmål-tracker** — visuell progress mot dagsmål/veckamål med countdown

## Förbättringar

- [ ] **Bonus "What-if"-simulator** — admin justerar bonusparametrar, ser effekt i realtid
- [ ] **Skiftbyte-PDF automatgenerering** — PDF vid skiftslut, länk i UI
- [ ] **Operatörsnärvaro-tracker** — kalendervy baserad på rebotling_ibc-data
- [ ] **Stopporsak-drill-down** — klicka på Pareto-stapel → se detaljer (tid, operatör, kommentar)

## Nya sidor

- [ ] **Månadsrapport** (`/rapporter/manad`) — auto-genererad sammanfattning, PDF-export
- [ ] **Skiftplaneringsvy** (`/admin/skiftplan`) — kalendervy, operatörer per skift

## Buggjakt

- [ ] **Bug Hunt #50** — granska session #46 commits (Skiftrapport + Kvalitet deep-dive)
