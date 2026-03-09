# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Rebotling-fokus (ägarens prioritet)

- [x] **Pareto-diagram stopporsaker** — horisontellt 80/20-diagram med kumulativ linje (d8c4356)
- [x] **Cykeltid per operatör** — grouped bar chart + ranking-tabell med median (3327f20)
- [ ] **Annotationer i grafer** — markera ut driftstopp, helgdagar i tidslinjen på statistiksidan
- [ ] **Skiftrapport per operatör** — filtrerbar skiftrapport per specifik operatör

## Förbättringar

- [ ] **Bonus "What-if"-simulator** — admin justerar bonusparametrar, ser effekt i realtid
- [ ] **Skiftbyte-PDF automatgenerering** — PDF vid skiftslut, länk i UI
- [ ] **Operatörsnärvaro-tracker** — kalendervy baserad på rebotling_ibc-data
- [ ] **IBC-kvalitets deep-dive** — bryt ner ej-godkända per avvisningsorsak

## Nya sidor

- [ ] **Månadsrapport** (`/rapporter/manad`) — auto-genererad sammanfattning, PDF-export
- [ ] **Skiftplaneringsvy** (`/admin/skiftplan`) — kalendervy, operatörer per skift

## Buggjakt

- [ ] **Bug Hunt #50** — granska session #45 commits (Pareto + Cykeltid) för nya buggar
