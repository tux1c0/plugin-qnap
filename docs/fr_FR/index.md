Description 
===

Plugin permettant la supervision de NAS QNAP.

Installationn 
===

Le plugin, une fois installé, doit être activé. Il installera les dépéndances nécessaires au bon fonctionnement.
Sur Debian 8, des problèmes de dépendances sont parfois bloquants pour le bon fonctionnement du plugin (erreur 500). Il n'y a pas de solution à date. 


Configuration
===

### QNAP 

Le NAS doit avoir le SNMP et SSH activé afin de pouvoir récupérer des informations.

Pour activer le SNMP, il faut aller dans la page d'administration SNMP, puis :

-   Activer le service

-   Choisir la version v1 ou v2 du protocole

-   Définir une communauté SNMP

-   Sauvegarder la configuration

Pour activer le SSH, il faut aller dans la page d'administration Telnet/SSH, puis :

-   Permettre la connexion SSH

-   Choisir le port (généralement le 22)

-   Sauvegarder la configuration

### Plugin

Tous les éléments suivants sont obligatoires pour avoir le plugin fonctionnel

-   IP : adresse IP du NAS

-   SSH (login, mot de passe et port de connexion SSH du NAS définit au-dessus)

-   SNMP : communauté et version SNMP du NAS définie précédemment

-	Utiliser uniquement le SNMP (désactive le SSH) : cocher la case désactive le protocole SSH. Certaines fonctions ne seront plus disponibles (redémarrer, arrêt, build QNAP, version d'OS, CPU)

Sauvegarder la configuration. Le module va commencer à poller toutes les 15 minutes le NAS.

Changelog
===
-	25/08/2019 : Ajout du changelog, passage v4, bugfixes, nouvelle branche pour la v3 qui n'évoluera pas mais qui sera disponible