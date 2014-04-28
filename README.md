CiviCRM Member Role Sync
------------------------

The *CiviCRM Member Role Sync* plugin allows you to synchronize CiviCRM memberships with WordPress roles. This enables you to have, among other things, members-only content on your website that is only accessible to current members defined by the rules and types you have set up in CiviCRM. 

This plugin is a fork of the [GitHub repo](https://github.com/tadpolecc/civi_member_sync) written by [Tadpole Collective](https://tadpole.cc) and  originally developed by [Jag Kandasamy](http://www.orangecreative.net). 

**Please note:** This plugin may not be functional at present. It is under development at the moment. The roadmap for this development phase is to make role syncing instantaneous rather than relying on login, logout or cron events happening.

## Installation ##

There are two ways to install from GitHub:

#### ZIP Download ####

If you have downloaded *CiviCRM Member Role Sync* as a ZIP file from this GitHub repository, do the following to install and activate the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/civicrm_member_sync`
2. Make sure *CiviCRM* is activated
3. Activate the plugin

#### git clone ####

If you have cloned the code from GitHub, it is assumed that you know what you're doing!

## Configuration ##

Before you get started, be sure to have created all of your membership types and membership status rules for CiviMember as well as the WordPress role(s) you would like to synchronize them with.

**Note:** Only one CiviCRM membership type can synchronize with one WordPress role since a WordPress user can have only one role in WordPress.

**Note:** At present, this plugin will only sync membership roles on user login, user logout and on a daily basis.

1. Visit the plugin's admin page at *Settings* --> *CiviCRM Member Role Sync*.
2. Click on *Add Association Rule* to create a rule. You will need to create a rule for every CiviCRM membership type you would like to synchronize. For every membership type, you will need to determine the CiviMember states that define the member as "current" thereby granting them the appropriate WordPress role. It is most common to define *New*, *Current* & *Grace* as current. Similarly, select which states represent the "expired" status thereby removing the WordPress role from the user. It is most common to define *Expired*, *Pending*, *Cancelled* & *Deceased* as the expired status. Also set the role to be assigned if the membership has expired in "Expiry Role".
3. It may sometimes be necessary to manually synchronize users. Click on the "Manually Synchronize" tab on the admin page to do so. You will likely use this when you initially configure this plugin to synchronize your existing users.

Be sure to test this plugin thoroughly before using it in a production environment. At minimum, you should log in as a test user to ensure you have been granted an appropriate role when that user is given membership. Then take away the membership for the user in their CiviCRM record, log back in as the test user, and make sure you no longer have that role.