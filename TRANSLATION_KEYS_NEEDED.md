# Hiányzó Fordítási Kulcsok - Google Calendar Sync

## Angol (frontend/public/locales/en/admin.json)

Hozzáadandó a fájl végéhez a `}` elé:

```json
  "googleCalendarSync": {
    "title": "Google Calendar Sync",
    "description": "Manage bidirectional synchronization between internal calendar and Google Calendar",
    "configurations": "Sync Configurations",
    "syncLogs": "Sync Logs",
    "newConfig": "New Configuration",
    "editConfig": "Edit Configuration",
    "configDeleted": "Configuration Deleted",
    "configDeletedDescription": "Sync configuration has been successfully deleted",
    "configCreated": "Configuration Created",
    "configCreatedDescription": "Sync configuration has been successfully created",
    "configUpdated": "Configuration Updated",
    "configUpdatedDescription": "Sync configuration has been successfully updated",
    "confirmDelete": "Are you sure you want to delete this configuration?",
    "deleteError": "Failed to delete configuration",
    "saveError": "Failed to save configuration",
    "noConfigs": "No Sync Configurations",
    "noConfigsDescription": "Create your first sync configuration to get started",
    "name": "Configuration Name",
    "namePlaceholder": "e.g., Main Gym - Primary Calendar",
    "calendarId": "Google Calendar ID",
    "room": "Room",
    "selectRoom": "Select room...",
    "syncDirection": "Sync Direction",
    "direction": {
      "import": "Import Only",
      "export": "Export Only",
      "both": "Bidirectional"
    },
    "syncEnabled": "Sync Enabled",
    "status": {
      "pending": "Pending",
      "in_progress": "In Progress",
      "completed": "Completed",
      "failed": "Failed",
      "cancelled": "Cancelled"
    },
    "lastSync": "Last Sync",
    "import": "Import",
    "export": "Export",
    "importWizard": "Import Wizard",
    "exportWizard": "Export Wizard",
    "startDate": "Start Date",
    "endDate": "End Date",
    "autoResolveConflicts": "Auto-resolve Conflicts (skip)",
    "overwriteExisting": "Overwrite Existing Events",
    "importWarning": "Import Warning",
    "importWarningDescription": "This will import events from Google Calendar. Events already synced from this system will be skipped.",
    "exportWarning": "Export Warning",
    "exportWarningDescription": "This will export events to Google Calendar. Make sure the date range is correct.",
    "startImport": "Start Import",
    "startExport": "Start Export",
    "importCompleted": "Import Completed",
    "importCompletedDescription": "Events have been successfully imported from Google Calendar",
    "exportCompleted": "Export Completed",
    "exportCompletedDescription": "Events have been successfully exported to Google Calendar",
    "importFailed": "Import Failed",
    "exportFailed": "Export Failed",
    "conflictsDetected": "Conflicts Detected",
    "conflictsDetectedDescription": "{{count}} conflicts were found that require your attention",
    "conflictingEvents": "Conflicting Events",
    "minutesOverlap": "minutes overlap",
    "skipImport": "Skip Import",
    "resolveConflicts": "Resolve Conflicts",
    "conflictsResolved": "Conflicts Resolved",
    "conflictsResolvedDescription": "All conflicts have been successfully resolved",
    "resolveError": "Failed to resolve conflicts",
    "eventsCreated": "created",
    "eventsUpdated": "updated",
    "eventsSkipped": "skipped",
    "eventsFailed": "failed",
    "noEvents": "No events",
    "created": "created",
    "updated": "updated",
    "skipped": "skipped",
    "failed": "failed",
    "exportingEvents": "Exporting Events...",
    "exporting": "Exporting...",
    "pleaseWait": "Please wait while we export your events",
    "errorsOccurred": "Errors Occurred",
    "noLogs": "No Sync Logs",
    "noLogsDescription": "No synchronization operations have been performed yet",
    "operation": {
      "import": "Import",
      "export": "Export"
    },
    "configuration": "Configuration",
    "dateRange": "Date Range",
    "results": "Results",
    "conflicts": "Conflicts",
    "completed": "Completed",
    "testConnection": "Test",
    "enterCalendarId": "Please enter a Calendar ID first",
    "connectionSuccess": "Connection Successful",
    "connectionSuccessDescription": "Successfully connected to Google Calendar",
    "connectionFailed": "Connection Failed",
    "newConfigDescription": "Create a new Google Calendar sync configuration",
    "editConfigDescription": "Modify the sync configuration settings"
  }
```

## Magyar (frontend/public/locales/hu/admin.json)

```json
  "googleCalendarSync": {
    "title": "Google Naptár Szinkronizálás",
    "description": "Kétirányú szinkronizálás kezelése a belső és Google naptár között",
    "configurations": "Szinkronizálási Konfigurációk",
    "syncLogs": "Szinkronizálási Logok",
    "newConfig": "Új Konfiguráció",
    "editConfig": "Konfiguráció Szerkesztése",
    "configDeleted": "Konfiguráció Törölve",
    "configDeletedDescription": "A szinkronizálási konfiguráció sikeresen törölve",
    "configCreated": "Konfiguráció Létrehozva",
    "configCreatedDescription": "A szinkronizálási konfiguráció sikeresen létrehozva",
    "configUpdated": "Konfiguráció Frissítve",
    "configUpdatedDescription": "A szinkronizálási konfiguráció sikeresen frissítve",
    "confirmDelete": "Biztosan törölni szeretnéd ezt a konfigurációt?",
    "deleteError": "Konfiguráció törlése sikertelen",
    "saveError": "Konfiguráció mentése sikertelen",
    "noConfigs": "Nincs Szinkronizálási Konfiguráció",
    "noConfigsDescription": "Hozd létre az első szinkronizálási konfigurációt",
    "name": "Konfiguráció Neve",
    "namePlaceholder": "pl.: Főcsarnok - Elsődleges Naptár",
    "calendarId": "Google Naptár ID",
    "room": "Helyszín",
    "selectRoom": "Válassz helyszínt...",
    "syncDirection": "Szinkronizálás Iránya",
    "direction": {
      "import": "Csak Import",
      "export": "Csak Export",
      "both": "Kétirányú"
    },
    "syncEnabled": "Szinkronizálás Engedélyezve",
    "status": {
      "pending": "Függőben",
      "in_progress": "Folyamatban",
      "completed": "Befejezve",
      "failed": "Sikertelen",
      "cancelled": "Megszakítva"
    },
    "lastSync": "Utolsó Szinkronizálás",
    "import": "Importálás",
    "export": "Exportálás",
    "importWizard": "Import Varázsló",
    "exportWizard": "Export Varázsló",
    "startDate": "Kezdő Dátum",
    "endDate": "Befejező Dátum",
    "autoResolveConflicts": "Konfliktusok Automatikus Feloldása (kihagyás)",
    "overwriteExisting": "Meglévő Események Felülírása",
    "importWarning": "Import Figyelmeztetés",
    "importWarningDescription": "Ez a Google Naptárból importál eseményeket. A már ebből a rendszerből szinkronizált események ki lesznek hagyva.",
    "exportWarning": "Export Figyelmeztetés",
    "exportWarningDescription": "Ez a Google Naptárba exportálja az eseményeket. Győződj meg róla, hogy a dátumtartomány helyes.",
    "startImport": "Import Indítása",
    "startExport": "Export Indítása",
    "importCompleted": "Import Befejezve",
    "importCompletedDescription": "Az események sikeresen importálva lettek a Google Naptárból",
    "exportCompleted": "Export Befejezve",
    "exportCompletedDescription": "Az események sikeresen exportálva lettek a Google Naptárba",
    "importFailed": "Import Sikertelen",
    "exportFailed": "Export Sikertelen",
    "conflictsDetected": "Ütközések Észlelve",
    "conflictsDetectedDescription": "{{count}} ütközés található, amely figyelmet igényel",
    "conflictingEvents": "Ütköző Események",
    "minutesOverlap": "perc átfedés",
    "skipImport": "Import Kihagyása",
    "resolveConflicts": "Konfliktusok Feloldása",
    "conflictsResolved": "Konfliktusok Feloldva",
    "conflictsResolvedDescription": "Minden konfliktus sikeresen feloldva",
    "resolveError": "Konfliktusok feloldása sikertelen",
    "eventsCreated": "létrehozva",
    "eventsUpdated": "frissítve",
    "eventsSkipped": "kihagyva",
    "eventsFailed": "sikertelen",
    "noEvents": "Nincs esemény",
    "created": "létrehozva",
    "updated": "frissítve",
    "skipped": "kihagyva",
    "failed": "sikertelen",
    "exportingEvents": "Események Exportálása...",
    "exporting": "Exportálás...",
    "pleaseWait": "Kérlek várj, amíg az eseményeket exportáljuk",
    "errorsOccurred": "Hibák Történtek",
    "noLogs": "Nincs Szinkronizálási Log",
    "noLogsDescription": "Még nem volt szinkronizálási művelet",
    "operation": {
      "import": "Importálás",
      "export": "Exportálás"
    },
    "configuration": "Konfiguráció",
    "dateRange": "Dátumtartomány",
    "results": "Eredmények",
    "conflicts": "Ütközések",
    "completed": "Befejezve",
    "testConnection": "Teszt",
    "enterCalendarId": "Kérlek először add meg a Naptár ID-t",
    "connectionSuccess": "Kapcsolat Sikeres",
    "connectionSuccessDescription": "Sikeresen csatlakozva a Google Naptárhoz",
    "connectionFailed": "Kapcsolat Sikertelen",
    "newConfigDescription": "Új Google Naptár szinkronizálási konfiguráció létrehozása",
    "editConfigDescription": "Szinkronizálási konfiguráció beállításainak módosítása"
  }
```

## Használat

1. Nyisd meg: `frontend/public/locales/en/admin.json`
2. Add hozzá a fenti angol fordításokat
3. Nyisd meg: `frontend/public/locales/hu/admin.json`
4. Add hozzá a fenti magyar fordításokat

## Megjegyzés

Ezeket a kulcsokat használják a Google Calendar Sync komponensek:
- GoogleCalendarSyncPage
- SyncConfigList
- SyncConfigDialog
- ImportWizard
- ExportWizard
- SyncLogsViewer
