Bonjour,

Vous trouverez  sur Github l’arborescence et quelques fichiers de l’application Zen.

Cette  application est le noyau de Stayfilm. Elle comprend la business logic du réseau social et l'accès á la base de donnée. Elle ne comprend pas le framework MVC.

Mes choix techniques pour l’architecture de Stayfilm furent largement conduit par l’obligation d’utiliser Codeigniter comme framework MVC. Je pensais que Symfony serai plus adapté sur le moyen et long-terme.

Pour contourner le  problème, j’ai donc décidé de séparer la couche MVC (application Cool) de la couche Services (Zen) en créant deux repositories git,  pour embrasser l’idée de Separations of concern et  pour faciliter la mise en place de Test Driven Development.

Stayfilm utilise comme base de donnée principale Cassandra, ce qui fut une grosse erreur comme nous nous en sommes aperçu un peu plus tard. J’estime que nous avons perdu 30% de notre temps à travailler avec une base de donnée nosql.

J’ai dû développer un ORM maison comme couche d’abstraction d’accès á la BD, Doctrine ou Propel n’offrant pas de connecteur pour Cassandra. Gráce á cet ORM, nous avons pu migrer pour Mysql 1 an plus tard.

Avec Jenkins et Ant, j’ai mis en place l’idée de Continuous Integration et mise á disposition de artefacts. L’idée était de déployer sur un serveur de tests la nouvelle version du site web, après avoir analysé le code avec une  suite d’outils (codesniffer, phpmd, pdepend etc…) et exécuté les testes unitaires. Sur ce serveur de teste étaient exécutés les tests fonctionnels automatisés (Selenium). Si tous les tests passaient, le build était considéré correct et pouvait être téléchargé pour le futur déploiement sur le serveur de staging dans le cloud Windows Azure, avant mise en production.

Si le code de Zen pourrait être nettement amélioré, clarifié et optimisé et ne suit pas toujours les bonnes pratiques notamment concernant l’autoloading,  je pense que l’architecture choisie fut plutôt une réussite.

Vous trouverez en pièces jointes la structure du projet et quelques fichiers avec des exemples de codes.


Voila,
