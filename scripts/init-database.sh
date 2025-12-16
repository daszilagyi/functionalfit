#!/bin/bash

# FunctionalFit Calendar - Database Initialization Script
# This script initializes the database with tables and seed data

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}FunctionalFit - Database Initialization${NC}"
echo -e "${GREEN}========================================${NC}"

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    if [ -d "backend" ]; then
        cd backend
    else
        echo -e "${RED}Error: Please run this script from the project root or backend directory${NC}"
        exit 1
    fi
fi

echo -e "${YELLOW}Checking .env configuration...${NC}"

if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Creating .env from .env.example...${NC}"
    cp .env.example .env
    php artisan key:generate
    echo -e "${GREEN}.env file created. Please edit it with your database credentials.${NC}"
    echo -e "${RED}Run this script again after configuring .env${NC}"
    exit 0
fi

echo -e "${YELLOW}Running database migrations...${NC}"

# Drop all tables and re-run migrations (fresh install)
read -p "Do you want to reset the database? This will DELETE all data! (y/N): " RESET
if [ "$RESET" = "y" ] || [ "$RESET" = "Y" ]; then
    php artisan migrate:fresh --force
else
    php artisan migrate --force
fi

echo -e "${GREEN}Migrations completed!${NC}"

echo -e "${YELLOW}Seeding database with initial data...${NC}"

php artisan db:seed --force

echo -e "${GREEN}Database seeded!${NC}"

echo -e "${YELLOW}Creating storage link...${NC}"

php artisan storage:link 2>/dev/null || true

echo -e "${GREEN}Storage linked!${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Database Initialization Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Created tables:"
echo -e "  - users (with 3 test users: admin, staff, client)"
echo -e "  - class_templates"
echo -e "  - class_occurrences"
echo -e "  - participants"
echo -e "  - rooms"
echo -e "  - sites"
echo -e "  - email_templates (9 templates)"
echo -e "  - email_logs"
echo -e "  - pass_types"
echo -e "  - user_passes"
echo -e "  - pricing_rules"
echo -e "  - calendar_changes"
echo -e "  - settlements"
echo -e "  - And more..."
echo ""
echo -e "Default users:"
echo -e "  Admin:  ${YELLOW}admin@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo -e "  Staff:  ${YELLOW}staff@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo -e "  Client: ${YELLOW}client@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo ""
