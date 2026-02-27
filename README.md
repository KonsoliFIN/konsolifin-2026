# konsolifin-2026

Tässä repossa on koodit, joiden varassa KonsoliFIN.net-sivusto pyörii.

## Mitä tarvitset kehitysympäristön pystyttämiseen

Jotta voit pyörittää tässä repossa olevaa sivua omalla koneellasi tarvitset:

- Docker Desktopin tai OrbStackin (<https://docs.docker.com/desktop/> tai <https://orbstack.dev/>)
- make, kuuluu ainakin Macin peruskehitystyökaluihin ja Linuxin oletusasennukseen
- Git ja GitHub-käyttäjätunnus
- jokin kehitystyökalu on kiva, esim. VSCode (<https://code.visualstudio.com/>)

## Asentaminen

Kopioi koodit omalle koneellesi, esim. komennolla
`git clone git@github.com:KonsoliFIN/konsolifin-2026.git`, joka luo hakemiston
konsolifin-2026 ja kopioi sen alle kaikki koodit. Vaihtoehtoisesti voit ladata
kaiken zip-tiedostona ja purkaa sen haluamaasi paikkaan.

Tämän jälkeen avaa komentorivi kopioidun kansion juuressa ja anna komento `make`.
Tämä komento rakentaa docker containerit, asentaa kaikki Drupalin tarvitsemat
kirjastot, luo palvelimen ja sille pääkäyttäjän sekä konfiguroi sivuston asetukset.

Tämän jälkeen voit kirjautua testipalvelimelle joko osoitteessa http://localhost:8080
tai mitä OrbStack onkaan päättänyt sivustolle antaa osoitteeksi (esim.
https://web.konsolifin-2026.orb.local/). Käyttäjätunnus ja salasana ovat molemmat
`admin`.

## Sammuttaminen ja käynnistäminen

Ensimmäisen asennuskerran jälkeen Dockerin voi käynnistää uudelleen komennolla `make start` ja
sammuttaa komennolla `make stop`.

## Sivuston päivittäminen

Jos Drupal-core tai yksittäisiä moduleita pitää päivittää, tämä onnistuu komennolla
`make update`. Tämä tosin toimii vain, jos olet käyttänyt gitiä koodin kloonaamiseen.

## Debuggaaminen VSCodella

Jos haluat debugata koodia, ota docker-compose.yml -tiedoston lopusta risuaita pois
rivin `# PHP_INI_XDEBUG__START_WITH_REQUEST: 1` alusta ja käynnistä containerit.

Drupal-kansiossa oleva .vscode/launch.json -tiedosto sisältää konfiguraatiot
PHP-koodin debuggaamiseen. Esimerkiksi VS Codella debuggaus toimii parhaiten,
kun avaa käsiteltäväksi alikansion 'drupal', eikä edes yritä tehdä sitä koko
repon juuresta.

VS Coden laajennus 'PHP Debug' lähtee toimimaan aika lailla heittämällä.
