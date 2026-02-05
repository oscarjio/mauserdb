╔═══════════════════════════════════════════════════════════════╗
║                  NOREKO DEPLOY SCRIPTS                        ║
║                   Snabbguide för dig                          ║
╔═══════════════════════════════════════════════════════════════╗

📁 FILER I DENNA MAPP:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ deploy-to-prod.sh      → Huvudscript för deploy till prod
✅ quick-deploy.sh         → Snabb deploy utan säkerhetskontroller  
✅ rollback-prod.sh        → Återställ från backup om något går fel
✅ SETUP-GUIDE.md          → FULLSTÄNDIG guide med Apache-config
✅ README.txt              → Denna fil


🚀 SNABBSTART:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Läs SETUP-GUIDE.md för fullständig installation

2. På servern, gör scripts körbara:
   chmod +x deploy-scripts/*.sh

3. Deploya till produktion:
   sudo ./deploy-scripts/deploy-to-prod.sh


💡 KONCEPT:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Du jobbar i:    /var/www/noreko-dev/
Live-sajten:    /var/www/noreko-prod/
Backups:        /var/www/noreko-backups/

När du kör deploy-to-prod.sh:
  1. Skapar backup av prod
  2. Bygger production-version av frontend
  3. Kopierar allt från dev till prod
  4. Klar!


🎯 VANLIGASTE KOMMANDON:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

# Deploya till produktion (säkert med backup)
sudo ./deploy-scripts/deploy-to-prod.sh

# Snabb deploy
sudo ./deploy-scripts/quick-deploy.sh

# Återställ om något går fel
sudo ./deploy-scripts/rollback-prod.sh


⚠️  VIKTIGT:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

→ Kör alltid scripts som sudo (behövs för permissions)
→ Testa alltid i dev innan deploy
→ Backups skapas automatiskt
→ Senaste 10 backups sparas, äldre raderas


📖 NÄSTA STEG:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Öppna SETUP-GUIDE.md och följ steg-för-steg instruktionerna!

Den innehåller:
  • Apache Virtual Host konfiguration
  • Mappstruktur
  • Felsökningsguide
  • Checklistor

Lycka till! 🎉
