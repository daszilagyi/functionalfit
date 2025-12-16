# Changelog

Az összes jelentős változás ebben a fájlban van dokumentálva.

A formátum a [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) alapján készült.

## [0.1.0-beta] - 2025-12-16

### Első nyilvános béta verzió

Ez az első tesztelésre kiadott verzió, amely tartalmazza az alkalmazás összes alapfunkcióját.

### Hozzáadva

#### Naptár és Foglalás
- Interaktív naptár nézet (heti, napi, lista)
- Drag & drop esemény mozgatás adminoknak
- Valós idejű szabad helyek megjelenítése
- Több helyszín (site) támogatása szűrővel
- Mobilbarát reszponzív dizájn

#### Csoportos Órák Kezelése
- Óra sablonok (ClassTemplate) ismétlődő órákhoz
- Óra előfordulások (ClassOccurrence) egyedi módosítási lehetőséggel
- Résztvevők kezelése (participants)
- Várólistás rendszer telített órákhoz
- Automatikus várólistáról felhozás lemondáskor

#### Felhasználókezelés (RBAC)
- Három szerepkör: Admin, Staff, Client
- Laravel Policy alapú jogosultságkezelés
- Sanctum token alapú autentikáció
- Jelszó visszaállítás email funkcióval

#### Bérlet és Árképzés
- Többféle bérlet típus (pass types)
- Ügyfél-specifikus árazás (price codes)
- Bérlet egyenleg követés
- Árkalkulátor foglaláskor

#### Email Értesítések
- 9 testreszabható email sablon
- HTML és plain text verzió támogatás
- Változók támogatása ({{user.name}}, {{class.title}}, stb.)
- Sablon verziókezelés és visszaállítás
- Queue alapú email küldés retry logikával
- Email log és audit trail

#### Google Calendar Integráció
- Kétirányú szinkron Google Naptárral
- Service Account alapú hitelesítés
- Automatikus esemény létrehozás/frissítés/törlés
- extendedProperties alapú ID mapping

#### Admin Funkciók
- Felhasználók kezelése
- Termek (rooms) kezelése
- Helyszínek (sites) kezelése
- Email sablonok szerkesztése
- Riportok és statisztikák
- Excel export

#### Riportok
- Jelenlét kimutatás
- Edző munkaidő összesítő
- Foglalási statisztikák
- No-show arány kimutatás

#### Többnyelvűség
- Magyar (hu) - alapértelmezett
- Angol (en) támogatás
- i18next alapú fordítási rendszer

#### Technikai
- Laravel 11 backend
- React 18 + TypeScript frontend
- SQLite/MySQL adatbázis támogatás
- Docker támogatás
- Queue alapú háttérfolyamatok
- API verziókezelés (v1)

### Ismert problémák

- Google Calendar szinkron csak egyirányú (rendszer → GCal)
- Néhány régi teszt email job hibás címekre próbál küldeni
- A queue worker-t manuálisan kell indítani

### Következő verzióban tervezve

- Push értesítések
- Mobilalkalmazás (React Native)
- Online fizetés integráció (Stripe/SimplePay)
- Bővített riportok
- API rate limiting
- 2FA autentikáció

---

[0.1.0-beta]: https://github.com/daszilagyi/functionalfit/releases/tag/v0.1.0-beta
