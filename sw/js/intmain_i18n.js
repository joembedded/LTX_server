/* Minimalistische Internationalisierung, aehnlich i18n - (C) JoEmbedded.de
Fuer Details siehe JoEmDash. Dies hier ist nur ein elokale Variante ohne exports

Es gibt 2 Moeglichkeiten Texte zu uebersetzen:
1.) Eintrag 'll' in div/span oder irgendenen anderen HTML-Tag setzen
  z.B. <span ll="welcome">(Welcome to LTX!)</span> wuerde dann den innerHTML durch 'Welcom...'/'Willkommen...' ersetzen

2.) Per Funktion ll(), z.B.  ll('driverversions') dynamisch generiet (via JS)

Wichtig: Die dynamischen Texte werden erst beim Seitenupdate aktualisiert.
DIe Üebersetzungstabelle ist aber fuer beide Mglk. verwendbar.

Die Anzahl der extern verfuegbaren Sprachen in i18_availLang = [] eintragen
*/

  // Locale translations. Sucht alle Elemente mit Attribut ll='ident' mit "ident":"Inhalt" , auch in Bloecken
  const version = 'V0.15 / 01.03.2024' // global
  // List of available Languages (CaseIndependent):
  const i18_availLang = ['EN - English', 'DE - Deutsch']    // global- 
  const i18_defaultLang = 'en'   // Fallback/Default (Lowercase)
  let i18_currentLang = 'en' // (Lowercase)

  const translations = {
    // EN
    en: {
      // Teil 1: JS-generierte Texte ll('key')
      "adminrights": "Administrator Rights",
      "limitedrights": "Limited Rights",
      "positionview": "Position View",
      "age": "Age",
      "linesdata": "Lines&nbsp;Data",
      "guestdevice": "Guest&nbsp;Device",

      // Teil 2: HTML-tagged ll='key'
      "welcome": "Welcome to LTX!",
      "hello": "Hello",
      "devices": "Devices",
      "infos":  "Infos",
      "menue": "Menue",
      "userprofile": "User Profile",
      "addowndevice": "Add own Device",
      "addguestdevice": "Add Guest Device",
      "removedevice": "Remove Device",
    },

    // DE
    de: {
      // Teil 1
      "adminrights": "Administrator Rechte",
      "limitedrights": "Begrenzte Rechte",
      "positionview": "Positionsanzeige",
      "age": "Alter",
      "linesdata": "Datenzeilen",
      "guestdevice": "Gast-Gerät",

      // Teil 2
      "welcome": "Wilkommen bei LTX!",
      "hello": "Hallo",
      "devices": "Geräte",
      "infos":  "Infos",
      "menue": "Menue",
      "userprofile": "Benutzerprofil",
      "addowndevice": "Eigenes Gerät hinzufügen",
      "addguestdevice": "Gast-Gerät hinzufügen",
      "removedevice": "Gerät entfernen",
    },
  }

/* Einzelnen String uebersetzen */
function ll(txt){
  const nc = translations[i18_currentLang][txt] // Preset Texts
  if(nc !== undefined) return nc
  console.log(`ll('${i18_currentLang}:${txt}') not found!`)
  return `(??? ${i18_currentLang}:'${txt}')` // NOT FOUND
}

/* Uebersetzt datierte Text ggfs. nach obiger Tabelle, relevant nur erste 2 Buchstaben im lowercase */
function i18localize(newLang) {
  let pageLang
  const sul = newLang.substr(0,2).toLowerCase() // User-Selectavle UpperCase
  for(let i=0;i<i18_availLang.length;i++){
    const ilang = i18_availLang[i]
    if(ilang.substring(0,2).toLowerCase() == sul) pageLang = sul
  }
  if(pageLang === undefined){
    pageLang = i18_defaultLang
    console.warn(`i18: New Language:'${newLang}' not found, Fallback:'${pageLang}'`)
  }

  const elements = document.querySelectorAll('[ll]')
  const lnga= translations[pageLang] // Preset Texts

  elements.forEach((element) => {
    const key = element.getAttribute('ll')
    const nc = lnga[key] // Preset Texts
     //console.log(key,nc) // Dbg - gibt alle key-values aus
    if(nc !== undefined) element.innerHTML = nc
    else console.warn(`i18n.js: Key:'${key}', Language:'${pageLang}' not found!`)
  })
  const htmlElement = document.querySelector('html')
  htmlElement.setAttribute('lang', pageLang)
  i18_currentLang = pageLang
}
/***/

