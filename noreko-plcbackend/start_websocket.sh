#!/bin/bash
#
# Start WebSocket Server for Real-time Bonus Tracking
#

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Bonus WebSocket Server Startup${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP is not installed${NC}"
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}✓${NC} PHP detected: $PHP_VERSION"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}⚠${NC}  Composer not found in PATH"
    echo -e "   Will try to use local composer.phar"
fi

# Check if vendor directory exists
if [ ! -d "$SCRIPT_DIR/vendor" ]; then
    echo -e "${YELLOW}⚠${NC}  Dependencies not installed"
    echo -e "   Running: composer install"

    if command -v composer &> /dev/null; then
        composer install
    elif [ -f "$SCRIPT_DIR/composer.phar" ]; then
        php composer.phar install
    else
        echo -e "${RED}❌ Cannot install dependencies. Please run:${NC}"
        echo -e "   composer require cboden/ratchet"
        exit 1
    fi
fi

echo -e "${GREEN}✓${NC} Dependencies installed"

# Check if port 8080 is available
if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo -e "${RED}❌ Port 8080 is already in use${NC}"
    echo -e "   Kill the process or choose a different port"
    echo ""
    echo "   Processes using port 8080:"
    lsof -i :8080
    exit 1
fi

echo -e "${GREEN}✓${NC} Port 8080 is available"

# Check if database connection file exists
if [ ! -f "$SCRIPT_DIR/db.php" ]; then
    echo -e "${YELLOW}⚠${NC}  db.php not found"
    echo -e "   Make sure database configuration exists"
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Starting WebSocket Server...${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Server will run on: ${YELLOW}ws://localhost:8080${NC}"
echo -e "Dashboard: ${YELLOW}file://$SCRIPT_DIR/bonus_realtime_dashboard.html${NC}"
echo ""
echo -e "Press ${YELLOW}Ctrl+C${NC} to stop the server"
echo ""

# Start the server
cd "$SCRIPT_DIR"
php BonusWebSocketServer.php
