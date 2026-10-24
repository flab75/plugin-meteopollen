# Météo & Pollen

Deux tuiles pour le dashboard et les designs. **Une tuile = un équipement du plugin** : on en crée autant qu'on veut,
chacune avec son type, son objet parent et ses réglages, et on la place où l'on veut comme n'importe quel équipement.

| Type | Affiche | Lit |
|---|---|---|
| Pollens du jour | niveau du reste de la journée par espèce (aulne, bouleau, olivier, graminées, armoise, ambroisie) | Open-Meteo (Copernicus CAMS), gratuit, sans clé |
| Météo et pluie dans l'heure | température, pluie dans l'heure (barres Météo-France et Open-Meteo), risque de pluie aujourd'hui / demain, vent, UV, demain, vigilance | un équipement du plugin **Météo-France**, plus Open-Meteo |

## Créer une tuile

1. Plugins › Météo › Météo & Pollen › **Ajouter**, donner un nom.
2. Choisir l'**objet parent**, cocher **Activer** et **Visible**.
3. Choisir le **type**, puis l'**équipement lu** (Météo) et les réglages proposés.
4. Sauvegarder. La tuile apparaît sur le dashboard de son objet.

Pour Pollens et la pluie Open-Meteo, la position est celle de Jeedom (Réglages › Système › Configuration › Informations),
ou celle de l'équipement Météo-France pour la tuile Météo.

## Réglages

- **Météo** : source préférée pour « Pluie dans N min » (Météo-France, puis Open-Meteo, ou l'inverse).

## Copie de la localisation (tuile Météo)

**Défaut du plugin Météo-France** : à chaque sauvegarde de son équipement (bouton Sauvegarder, changement d'objet ou de
visibilité), il refait la recherche de la commune sur api-adresse.data.gouv.fr. Si ce service est lent ou ne répond pas, il
efface la latitude, la longitude et le code INSEE, sans autre signe qu'une ligne dans son log ; sa tuile affiche alors
« Erreur de configuration ». Il suffit en général de sauvegarder à nouveau l'équipement Météo-France plus tard. Un message
le rappelle à l'installation de Météo & Pollen.

Option **Copie de la localisation** cochée sur la tuile Météo : la dernière localisation complète de l'équipement lu
(commune, code postal, INSEE, latitude, longitude, département) est gardée et affichée sur la page de la tuile, avec sa date.
Elle est mise à jour toutes les 5 minutes si elle change ; une localisation vidée ne l'écrase pas. Rien n'est remis
automatiquement.

## Mises à jour

- Pollens et risque de pluie du jour : toutes les heures, ou par la commande **Rafraîchir**.
- Pluie dans l'heure Open-Meteo : toutes les 5 minutes.
- Les valeurs de l'équipement Météo-France suivent en direct.

## Commandes créées

- Pollens : une commande par espèce (g/m³, historisée), Rafraîchir.
- Météo : Pluie aujourd'hui, Pluie demain (%, historisées), 6 commandes de pluie dans l'heure Open-Meteo, Rafraîchir.

## Données envoyées

- Open-Meteo (pollens, pluie) : la latitude et la longitude.
- Météo-France : la position de l'équipement Météo-France, par le plugin Météo-France.
