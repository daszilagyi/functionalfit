import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import HttpBackend from 'i18next-http-backend'

i18n
  .use(HttpBackend)
  .use(initReactI18next)
  .init({
    lng: 'hu', // default language (Hungarian)
    fallbackLng: 'en',
    ns: ['common', 'auth', 'calendar', 'classes', 'staff', 'client', 'admin', 'public', 'settings'],
    preload: ['hu', 'en'], // Preload languages to ensure translations are ready
    defaultNS: 'common',
    backend: {
      loadPath: '/locales/{{lng}}/{{ns}}.json',
    },
    interpolation: {
      escapeValue: false, // React already escapes
    },
    react: {
      useSuspense: false, // Disable suspense to show translation keys until loaded
    },
  })

export default i18n
