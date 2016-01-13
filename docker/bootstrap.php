<?php

# TODO: some of the stuff here may change across environments, e.g. in php.ini
# display_errors could be enabled for developers.

define('ATOM_ROOT_DIR', '/atom');
define('ATOM_SRC_DIR', '/atom/src');

$env_vars = array(
    'ATOM_ES_HOSTS'         => 'elasticsearch:9200',
    'ATOM_MEMCACHE_HOSTS'   => 'memcached:11211',
    'ATOM_MYSQL_DSN'        => 'mysql:host=percona,port=3306,dbname=atom,charset=utf8',
    'ATOM_MYSQL_USERNAME'   => 'atom',
    'ATOM_MYSQL_PASSWORD'   => 'atom_12345',
);

#
# /apps/qubit/config/settings.yml
#

copy(ATOM_SRC_DIR.'/apps/qubit/config/settings.yml.tmpl', ATOM_SRC_DIR.'/apps/qubit/config/settings.yml');

#
# /config/propel.ini
#

touch(ATOM_SRC_DIR.'/config/propel.ini');

#
# /apps/qubit/config/gearman.yml
#

$gearman_yml = <<<EOT
all:
  servers:
    default: gearmand:4730
EOT;

file_put_contents(ATOM_SRC_DIR.'/apps/qubit/config/gearman.yml', $gearman_yml);

#
# /apps/qubit/config/app.yml
#

$app_yml = <<<EOT
all:
  download_timeout: 10
  htmlpurifier_enabled: false
  read_only: false
  upload_limit: -1
  cache_engine:
    class: sfMemcacheCache
    param:
      host: memcached
      port: 11211
      prefix: atom
      storeCacheInfo: true
      persistent: true
EOT;

file_put_contents(ATOM_SRC_DIR.'/apps/qubit/config/app.yml', $app_yml);

#
# /config/search.yml
#

$search_yml = <<<EOT
all:
  server:
    host: elasticsearch
    post: 9200
EOT;

file_put_contents(ATOM_SRC_DIR.'/config/search.yml', $search_yml);

#
# /config/config.php
#

$mysql_config = array(
  'all' => array(
    'propel' => array(
      'class' => 'sfPropelDatabase',
      'param' => array(
        'encoding' => 'utf8',
        'persistent' => true,
        'pooling' => true,
        'dsn' => 'mysql:dbname=atom;host=percona;charset=utf8',
        'username' => 'atom',
        'password' => 'atom_12345',
      ),
    ),
  ),
  'dev' => array(
    'propel' => array(
      'param' => array(
        'classname' => 'DebugPDO',
        'debug' => array(
          'realmemoryusage' => true,
          'details' => array(
            'time' => array('enabled' => true,),
            'slow' => array('enabled' => true, 'threshold' => 0.1,),
            'mem' => array('enabled' => true,),
            'mempeak' => array ('enabled' => true,),
            'memdelta' => array ('enabled' => true,),
          ),
        ),
      ),
    ),
  ),
  'test' => array(
    'propel' => array(
      'param' => array(
        'classname' => 'DebugPDO',
      ),
    ),
  ),
);

$config_php = "<?php\n\nreturn ".var_export($mysql_config, 1).";\n\n?>\n";
file_put_contents(ATOM_SRC_DIR.'/config/config.php', $config_php);

#
# php ini
#

$php_ini = <<<EOT
[PHP]

; For production! (but validate_timestamps enabled)

engine = on
short_open_tag = off
output_buffering = 4096
zlib.output_compression = off
expose_php = off
max_execution_time = 120
max_input_time = 120
memory_limit = 512M
output_buffering = 4096
error_reporting = E_ALL
display_errors = off
display_startup_errors = off
html_errors = on
log_errors = on
report_memleaks = on
variables_order = "GPCS"
request_order = "GP"
post_max_size = 72M
default_mimetype = "text/html"
default_charset = "UTF-8"
cgi.fix_pathinfo = 0
file_uploads = on
upload_max_filesize = 64M
max_file_uploads = 20
allow_url_fopen = on
allow_url_include = off

[Date]
date.timezone = America/Vancouver

[Session]
session.use_cookies = 1
session.use_only_cookies = 0

[apc]
apc.enabled = 1
apc.shm_size = 64M
apc.num_files_hint = 5000

[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.validate_timestamps = 1
opcache.max_accelerated_files = 100000
opcache.fast_shutdown = 1

[Assertion]
zend.assertions = -1

EOT;

file_put_contents(ATOM_ROOT_DIR.'/php.ini', $php_ini);

#
# fpm ini
#

$fpm_ini = <<<EOT
[global]

error_log = /proc/self/fd/2
daemonize = no

[atom]

; if we send this to /proc/self/fd/1, it never appears
access.log = /proc/self/fd/2
user = root
group = root

listen = [::]:9000

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

clear_env = no

; Ensure worker stdout and stderr are sent to the main error log.
catch_workers_output = yes
EOT;

file_put_contents(ATOM_ROOT_DIR.'/fpm.ini', $fpm_ini);
