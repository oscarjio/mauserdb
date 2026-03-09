# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Rebotling-fokus (ägarens prioritet)

- [PÅGÅR] **Pareto-diagram stopporsaker** — horisontellt 80/20-diagram med kumulativ linje (session #45)
- [PÅGÅR] **Cykeltid per operatör** — grouped bar chart + ranking-tabell med median (session #45)
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

- [ ] **Bug Hunt #49** — granska senaste commits för nya buggar (timeout, catchError, lifecycle)
