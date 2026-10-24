# Weather & Pollen

Two tiles for the dashboard and designs. **One tile = one equipment of the plugin**: create as many as you like,
each with its own type, parent object and settings, and place it like any other equipment.

| Type | Shows | Reads |
|---|---|---|
| Today's pollen | level for the rest of the day per species (alder, birch, olive, grass, mugwort, ragweed) | Open-Meteo (Copernicus CAMS), free, no key |
| Weather and rain within the hour | temperature, rain within the hour (Météo-France and Open-Meteo bars), chance of rain today / tomorrow, wind, UV, tomorrow, warnings | an equipment of the **Météo-France** plugin, plus Open-Meteo |

## Create a tile

1. Plugins › Weather › Météo & Pollen › **Add**, give it a name.
2. Choose the **parent object**, tick **Enable** and **Visible**.
3. Choose the **type**, then the **source equipment** (Weather) and the settings offered.
4. Save. The tile shows on its object's dashboard.

Pollen and Open-Meteo rain use Jeedom's location (Settings › System › Configuration › Information),
or the Météo-France equipment's location for the Weather tile.

## Settings

- **Weather**: preferred source for “Rain in N min” (Météo-France then Open-Meteo, or the reverse).

## Location copy (Weather tile)

**Météo-France plugin defect**: each time its equipment is saved (Save button, change of object or visibility), it looks the
town up again on api-adresse.data.gouv.fr. If that service is slow or does not answer, it erases the latitude, longitude and
INSEE code, with no other sign than a line in its log; its tile then shows “Erreur de configuration”. Saving the Météo-France
equipment again later usually fixes it. A message recalls it when Météo & Pollen is installed.

With **Location copy** ticked on the Weather tile, the last complete location of the read equipment (town, postcode, INSEE,
latitude, longitude, department) is kept and shown on the tile page, with its date. It is updated every 5 minutes when it
changes; an emptied location does not overwrite it. Nothing is put back automatically.

## Updates

- Pollen and today's chance of rain: every hour, or with the **Refresh** command.
- Open-Meteo rain within the hour: every 5 minutes.
- Values of the Météo-France equipment update live.

## Commands created

- Pollen: one command per species (g/m³, historized), Refresh.
- Weather: Rain today, Rain tomorrow (%, historized), 6 Open-Meteo rain-within-the-hour commands, Refresh.

## Data sent

- Open-Meteo (pollen, rain): latitude and longitude.
- Météo-France: the Météo-France equipment's location, through the Météo-France plugin.
