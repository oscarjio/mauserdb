# MauserDB Dev Log

Kort logg över vad som hänt — uppdateras automatiskt av Claude-agenter.

---

## 2026-03-03

### Auth-fix (commit ecc6b40)
- `fetchStatus()` returnerar nu `Observable<void>` istället för void
- `APP_INITIALIZER` använder `firstValueFrom(auth.fetchStatus())` — Angular väntar på HTTP-svar innan routing startar
- `catchError` returnerar `null` istället för `{ loggedIn: false }` — transienta fel loggar inte ut användaren
- `StatusController.php`: `session_start(['read_and_close'])` — PHP-session-låset släpps direkt, hindrar blockering vid sidomladdning

### Planerade förbättringar (agenterna jobbar på dessa)
- **Bonus-dashboard**: Realtidstrender, skiftprognos, motiverande UI för operatörer
- **My-bonus**: Bättre visualisering av eget bonusläge, historikgraf
- **Rebotling-statistik**: Veckojämförelse, skiftmålsprediktor, förbättrad heatmap
- **Rebotling-skiftrapport**: Bättre filtrering, sortering, sammanfattningskort
- **Rebotling-admin**: Bonusnivå-konfiguration, målhantering per veckodag
- **Production analysis**: Stopporsaksanalys, OEE deep-dive

---
