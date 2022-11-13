<?php
/**
 * en_us language for the Sonicpanel module.
 */
// Basics
$lang['Sonicpanelreseller.name'] = 'SonicPanel (Reseller)';
$lang['Sonicpanelreseller.description'] = 'Allows you to offer radio reseller accounts using SonicPanel. (Use regular SonicPanel module for client radio provisioning)';
$lang['Sonicpanelreseller.module_row'] = 'Server';
$lang['Sonicpanelreseller.module_row_plural'] = 'Servers';
$lang['Sonicpanelreseller.module_group'] = 'Server Group';
$lang['Sonicpanelreseller.tab_stats'] = 'Radio Login Information';
$lang['Sonicpanelreseller.tab_client_actions'] = 'Change Password';
$lang['Sonicpanelreseller.submit'] = 'Submit';

// Tab Stats
$lang['Sonicpanelreseller.tab_stats.info_heading.field'] = 'Field';
$lang['Sonicpanelreseller.tab_stats.info_heading.value'] = 'Value';
$lang['Sonicpanelreseller.tab_stats.info.radiousername'] = 'Reseller Radio Username';
$lang['Sonicpanelreseller.tab_stats.info.radiopassword'] = 'Reseller Radio Password';
$lang['Sonicpanelreseller.tab_stats.info.ipaddress'] = 'Radio IP';
$lang['Sonicpanelreseller.tab_stats.info.hostname'] = 'Radio Hostname';
$lang['Sonicpanelreseller.tab_stats.info.loginlink'] = 'Radio Login Link';
$lang['Sonicpanelreseller.tab_stats.info.loginbutton'] = 'Radio Login';
// Tab Actions
$lang['Sonicpanelreseller.tab_client_actions.change_password'] = "Change Passwords";
$lang['Sonicpanelreseller.tab_client_actions.radiopassword'] = "New Radio Password";

// Module management
$lang['Sonicpanelreseller.add_module_row'] = 'Add Server';
$lang['Sonicpanelreseller.manage.module_rows_title'] = 'Sonicpanel Servers';
$lang['Sonicpanelreseller.manage.module_rows_heading.name'] = 'Server Label';
$lang['Sonicpanelreseller.manage.module_rows_heading.ipaddress'] = 'IP Address';
$lang['Sonicpanelreseller.manage.module_rows_heading.hostname'] = 'Hostname';
$lang['Sonicpanelreseller.manage.module_rows_heading.options'] = 'Options';
$lang['Sonicpanelreseller.manage.module_groups_heading.name'] = 'Group Name';
$lang['Sonicpanelreseller.manage.module_groups_heading.servers'] = 'Server Count';
$lang['Sonicpanelreseller.manage.module_groups_heading.options'] = 'Options';
$lang['Sonicpanelreseller.manage.module_rows.edit'] = 'Edit';
$lang['Sonicpanelreseller.manage.module_groups.edit'] = 'Edit';
$lang['Sonicpanelreseller.manage.module_rows.delete'] = 'Delete';
$lang['Sonicpanelreseller.manage.module_groups.delete'] = 'Delete';
$lang['Sonicpanelreseller.manage.module_rows.confirm_delete'] = 'Are you sure you want to delete this server?';
$lang['Sonicpanelreseller.manage.module_groups.confirm_delete'] = 'Are you sure you want to delete this server group?';
$lang['Sonicpanelreseller.manage.module_rows_no_results'] = 'There are no servers.';
$lang['Sonicpanelreseller.manage.module_groups_no_results'] = 'There are no server groups.';

$lang['Sonicpanelreseller.order_options.first'] = 'First non-full server';

// Add-Edit Admin Module Page
$lang['Sonicpanelreseller.add_row.addserver'] = 'Add Sonicpanel Server';
$lang['Sonicpanelreseller.add_row.basic_title'] = 'Basic Settings';
$lang['Sonicpanelreseller.add_row.add_btn'] = 'Add Server';

$lang['Sonicpanelreseller.edit_row.editserver'] = 'Edit Sonicpanel Server';
$lang['Sonicpanelreseller.edit_row.basic_title'] = 'Basic Settings';
$lang['Sonicpanelreseller.edit_row.add_btn'] = 'Edit Server';

$lang['Sonicpanelreseller.row_meta.server_name'] = 'SonicPanel Name Label';
$lang['Sonicpanelreseller.row_meta.ipaddress'] = 'SonicPanel Server IP Address';
$lang['Sonicpanelreseller.row_meta.hostname'] = 'SonicPanel Server Hostname';
$lang['Sonicpanelreseller.row_meta.adminusername'] = 'SonicPanel Username (Root Account / Administrator Account)';
$lang['Sonicpanelreseller.row_meta.adminapikey'] = 'SonicPanel API Key';
$lang['Sonicpanelreseller.row_meta.usessl'] = 'Use SSL (required for certain SonicPanel features)';
$lang['Sonicpanelreseller.row_meta.useproxy'] = 'Use IPv6 to IPv4 Proxy (Useful if Blesta uses IPv6 but SonicPanel does not)';
$lang['Sonicpanelreseller.row_meta.hostname'] = 'SonicPanel Domain Hostname';

// Service fields
$lang['Sonicpanelreseller.service_field.radiousername'] = 'SonicPanel Reseller Radio Username';
$lang['Sonicpanelreseller.service_field.radiopassword'] = 'SonicPanel Reseller Radio Password';
$lang['Sonicpanelreseller.service_field.radiousername.tooltip'] = 'The username will only be updated locally within Blesta';
$lang['Sonicpanelreseller.service_field.radiopassword.tooltip'] = 'The password will be updated on SonicPanel';
$lang['Sonicpanelreseller.service_field.module_package_error'] = 'No radio package found on specified server! You must first add a package to SonicPanel.';
$lang['Sonicpanelreseller.service_field.module_configoptionmessage'] = 'Use Configurable Option Instead (Recommended)';

// Service info
$lang['Sonicpanelreseller.service_info.ipaddress'] = 'IP Address';
$lang['Sonicpanelreseller.service_info.hostname'] = 'Hostname';
$lang['Sonicpanelreseller.service_info.radiousername'] = 'SonicPanel Reseller Radio Username';
$lang['Sonicpanelreseller.service_info.radiopassword'] = 'SonicPanel Reseller Radio Password';
$lang['Sonicpanelreseller.service_info.options'] = 'Options';
$lang['Sonicpanelreseller.service_info.option_login'] = 'Log in';

// Errors
$lang['Sonicpanelreseller.!error.adminusername_valid'] = 'You must enter a Username.';
$lang['Sonicpanelreseller.!error.server_name_valid'] = 'You must enter a Server Label.';
$lang['Sonicpanelreseller.!error.ipaddress_valid'] = 'The IP Address appears to be invalid.';
$lang['Sonicpanelreseller.!error.hostname_valid'] = 'The Hostname Address appears to be invalid.';
$lang['Sonicpanelreseller.!error.adminapikey_valid'] = 'The API credentials appear to be invalid.';
$lang['Sonicpanelreseller.!error.api.internal'] = 'An internal error occurred, or the server did not respond to the request.';
$lang['Sonicpanelreseller.!error.module_row.missing'] = 'An internal error occurred. The module row is unavailable.';


$lang['Sonicpanelreseller.!error.radiousername.empty'] = "Username can't be empty.";
$lang['Sonicpanelreseller.!error.radiopassword.valid'] = 'Password must be at least 8 characters in length.';

//Package Fields
$lang['Sonicpanelreseller.package_fields.package'] = 'Package';
$lang['Sonicpanelreseller.!error.meta[package].valid'] = 'Empty SonicPanel Package Set.';
