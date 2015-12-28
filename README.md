Core plugins for osTicket
=========================

Core plugins for osTicket-1.8 and onward

Installing
==========

Clone this repo or download the zip file and place the contents into your
`include/plugins` folder

After cloning, `hydrate` the repo by downloading the third-party library
dependencies.

    php make.php hydrate

Building Plugins
================
Make any necessary additions or edits to plugins and build PHAR files with
the `make.php` command

    php -dphar.readonly=0 make.php build <plugin-folder>

This will compile a PHAR file for the plugin directory. The PHAR will be
named `plugin.phar` and can be dropped into the osTicket `plugins/` folder
directly.

Translating
===========

[![Crowdin](https://d322cqt584bo4o.cloudfront.net/osticket-plugins/localized.png)](http://i18n.osticket.com/project/osticket-plugins)

Translation service is being performed via the Crowdin translation
management software. The project page for the plugins is located at

http://i18n.osticket.com/projects/osticket-plugins
