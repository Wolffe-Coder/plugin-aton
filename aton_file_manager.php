<?php
/*
  Plugin Name: ATON File Manager
  Description: Файловый менеджер для официального сайта ООО "ОА "АТОН".
  Author: Wolffe
  Author URI: https://vk.com/eugene_wolffe
  Version: 1.0
 */
if (!defined('ATON_FILE_MANAGER_DIRNAME')) {
    define('ATON_FILE_MANAGER_DIRNAME', plugin_basename(dirname(__FILE__)));
}
define('ATON_FILE_MANAGER_PATH', plugin_dir_path(__FILE__));
if (!class_exists('mk_file_folder_manager')):
    class mk_file_folder_manager
    {
        protected $SERVER = 'http://ikon.digital/plugindata/api.php';
        var $ver = '1.0';
        /* Авто-загрузка Хуков */
        public function __construct()
        {
            add_action('admin_menu', array(&$this, 'ffm_menu_page'));
            add_action('admin_enqueue_scripts', array(&$this, 'ffm_admin_things'));
            add_action('wp_ajax_mk_file_folder_manager', array(&$this, 'mk_file_folder_manager_action_callback'));
            add_action('wp_ajax_mk_fm_close_fm_help', array($this, 'mk_fm_close_fm_help'));
            add_filter('plugin_action_links', array(&$this, 'mk_file_folder_manager_action_links'), 10, 2);
            do_action('load_filemanager_extensions');
            add_action('plugins_loaded', array(&$this, 'filemanager_load_text_domain'));
            /*
            Lokhal верификация Email
            */
            add_action('wp_ajax_mk_filemanager_verify_email', array(&$this, 'mk_filemanager_verify_email_callback'));
            add_action('wp_ajax_verify_filemanager_email', array(&$this, 'verify_filemanager_email_callback'));
            /*
            Загрузка Мультимедии
            */
            add_action('wp_ajax_mk_file_folder_manager_media_upload', array(&$this, 'mk_file_folder_manager_media_upload'));
             /* Новая функция */
             add_action('init', array(&$this, 'create_auto_directory'));
             /* Функция - Резервное Копирование */
             add_action('wp_ajax_mk_file_manager_backup', array(&$this, 'mk_file_manager_backup_callback'));
             add_action('wp_ajax_mk_file_manager_backup_remove', array(&$this, 'mk_file_manager_backup_remove_callback'));
             add_action('wp_ajax_mk_file_manager_single_backup_remove', array(&$this, 'mk_file_manager_single_backup_remove_callback'));
             add_action('wp_ajax_mk_file_manager_single_backup_logs', array(&$this, 'mk_file_manager_single_backup_logs_callback'));
             add_action('wp_ajax_mk_file_manager_single_backup_restore', array(&$this, 'mk_file_manager_single_backup_restore_callback'));
             $this->mk_file_manager_create_tables();
             register_activation_hook( __FILE__, array(&$this, 'mk_file_manager_create_tables'));
        }

         /* создание резервную таблицу */
        public function mk_file_manager_create_tables() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpfm_backup';
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE ".$table_name." (
                id int(11) NOT NULL AUTO_INCREMENT,
                backup_name text NULL,
                backup_date text NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            dbDelta( $sql );
            }
        }
        /* Резервное Копирование - Восстановление */
        public function mk_file_manager_single_backup_restore_callback() {
            $nonce = $_POST['nonce'];
            if(current_user_can('manage_options') && wp_verify_nonce( $nonce, 'wpfmbackuprestore' )) {
            global $wpdb;
            $fmdb = $wpdb->prefix.'wpfm_backup';
            $upload_dir = wp_upload_dir();
            $backup_dirname = $upload_dir['basedir'].'/wp-file-manager-pro/fm_backup/';
            $bkpid = (int) $_POST['id'];
            $result = array();
            $filesDestination = WP_CONTENT_DIR.'/';
            if($bkpid) {
                include('classes/files-restore.php');
                $restoreFiles = new aton_file_manager_files_restore();
                $fmbkp = $wpdb->get_row('select * from '.$fmdb.' where id = "'.$bkpid.'"');
                // case 1 - База данных
                if(file_exists($backup_dirname.$fmbkp->backup_name.'-db.sql.gz')) {
                    include('classes/db-restore.php');
                    $restoreDatabase = new Restore_Database($fmbkp->backup_name.'-db.sql.gz');
                    if($restoreDatabase->restoreDb()) {
                      $result[] = 'Резервная копия Базы данных успешно восстановлена.';
                    } else {
                      $result[] = 'Невозможно восстановить резервную копию Базы данных.';
                    }
                }
                // case 2 - Плагины
                if(file_exists($backup_dirname.$fmbkp->backup_name.'-plugins.zip')) {
                    $restorePlugins = $restoreFiles->extract($backup_dirname.$fmbkp->backup_name.'-plugins.zip',$filesDestination.'plugins');
                    if($restorePlugins) {
                      $result[] = 'Резервное копирования Плагинов успешно восстановлены.';
                    } else {
                      $result[] = 'Невозможно восстановить Плагины.';
                    }
                }
                // case 3 - Темы
                if(file_exists($backup_dirname.$fmbkp->backup_name.'-themes.zip')) {
                    $restoreThemes = $restoreFiles->extract($backup_dirname.$fmbkp->backup_name.'-themes.zip',$filesDestination.'themes');
                    if($restoreThemes) {
                      $result[] = 'Резервное копирование Темы успешно восстановлены.';
                    } else {
                      $result[] = 'Невозможно восстановить Темы.';
                    }
                }
                // case 4 - Загразка
                if(file_exists($backup_dirname.$fmbkp->backup_name.'-uploads.zip')) {
                    $restoreUploads = $restoreFiles->extract($backup_dirname.$fmbkp->backup_name.'-uploads.zip',$filesDestination.'uploads');
                    if($restoreUploads) {
                      $result[] = 'Загрузка резервной копии успешно восстановлена.';
                    } else {
                      $result[] = 'Невозможно восстановить загруженные файлы.';
                    }
                }
                // case 5 - Другое
                if(file_exists($backup_dirname.$fmbkp->backup_name.'-others.zip')) {
                    $restoreOthers = $restoreFiles->extract($backup_dirname.$fmbkp->backup_name.'-others.zip',$filesDestination);
                    if($restoreOthers) {
                      $result[] = 'Другие резервные копии успешно восстановлены.';
                    } else {
                      $result[] = 'Невозможно восстановить другие резервные копии.';
                    }
                }
            } else {
                $result[] = 'Резервная копия не найдена!';
            }
             echo (implode(',', $result));
            die;
         }
        }
        /* Резервное Копирование - Удаление */
        public function mk_file_manager_backup_remove_callback(){
            $nonce = $_POST['nonce'];
            if(current_user_can('manage_options') && wp_verify_nonce( $nonce, 'wpfmbackupremove' )) {
            global $wpdb;
            $fmdb = $wpdb->prefix.'wpfm_backup';
            $upload_dir = wp_upload_dir();
            $backup_dirname = $upload_dir['basedir'].'/wp-file-manager-pro/fm_backup/';
            $bkpRids = $_POST['delarr'];
            $isRemoved = false;
            if(isset($bkpRids)) {
                foreach($bkpRids as $bkRid) {
                    $fmbkp = $wpdb->get_row('select * from '.$fmdb.' where id = "'.$bkRid.'"');
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-db.sql.gz')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-db.sql.gz');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-others.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-others.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-plugins.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-plugins.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-themes.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-themes.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-uploads.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-uploads.zip');
                    }
                    // удаление из БД
                    $wpdb->delete($fmdb, array('id' => $bkRid));
                    $isRemoved = true;
                }
            }
            if($isRemoved) {
                echo 'Резервное копирование успешно удалено!';
            } else {
                echo 'Невозможно удалить резервную копию!';
            }
            die;
        }
        }
        /* Журналы Резервного Копирования */
        public function mk_file_manager_single_backup_logs_callback() {
            $nonce = $_POST['nonce'];
            if(current_user_can('manage_options') && wp_verify_nonce( $nonce, 'wpfmbackuplogs' )) {
            global $wpdb;
            $fmdb = $wpdb->prefix.'wpfm_backup';
            $upload_dir = wp_upload_dir();
            $backup_dirname = $upload_dir['basedir'].'/wp-file-manager-pro/fm_backup/';
            $bkpId = (int) $_POST['id'];
            $logs = array();
            $logMessage = '';
            if(isset($bkpId)) {
                    $fmbkp = $wpdb->get_row('select * from '.$fmdb.' where id = "'.$bkpId.'"');
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-db.sql.gz')) {
                        $size = filesize($backup_dirname.$fmbkp->backup_name.'-db.sql.gz');
                        $logs[] = 'Резервное копирование Базы данных сделано '.$fmbkp->backup_date.' ('.$fmbkp->backup_name.'-db.sql.gz) ('.$this->formatSizeUnits($size).')';
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-plugins.zip')) {
                        $size = filesize($backup_dirname.$fmbkp->backup_name.'-plugins.zip');
                        $logs[] = 'Резервное копирование Плагинов сделано '.$fmbkp->backup_date.' ('.$fmbkp->backup_name.'-plugins.zip) ('.$this->formatSizeUnits($size).')';
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-themes.zip')) {
                        $size = filesize($backup_dirname.$fmbkp->backup_name.'-themes.zip');
                        $logs[] = 'Резервное копирование Темы сделано '.$fmbkp->backup_date.' ('.$fmbkp->backup_name.'-themes.zip) ('.$this->formatSizeUnits($size).')';
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-uploads.zip')) {
                        $size = filesize($backup_dirname.$fmbkp->backup_name.'-uploads.zip');
                        $logs[] = 'Резервное копирование Загрузок сделано '.$fmbkp->backup_date.' ('.$fmbkp->backup_name.'-uploads.zip) ('.$this->formatSizeUnits($size).')';
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-others.zip')) {
                        $size = filesize($backup_dirname.$fmbkp->backup_name.'-others.zip');
                        $logs[] = 'Резервное копирование Другого сделано '.$fmbkp->backup_date.' ('.$fmbkp->backup_name.'-others.zip) ('.$this->formatSizeUnits($size).')';
                    }
            }
            $count = 1;
            $logMessage = '<h3 class="fm_console_log_pop log_msg_align_center">Logs</h3>';
            if(isset($logs)) {
                foreach($logs as $log) {
                    $logMessage .= '<p class="fm_console_success">('.$count++.') '.$log.'</p>';
                }
            } else {
                $logMessage .= '<p class="fm_console_error">Не найдено!</p>';
            }
            echo $logMessage;
            die;
        }
        }
       /* Возвращает Правильный Формат */
        public function formatSizeUnits($bytes) {
            if ($bytes >= 1073741824)
            {
                $bytes = number_format($bytes / 1073741824, 2) . ' GB';
            }
            elseif ($bytes >= 1048576)
            {
                $bytes = number_format($bytes / 1048576, 2) . ' MB';
            }
            elseif ($bytes >= 1024)
            {
                $bytes = number_format($bytes / 1024, 2) . ' KB';
            }
            elseif ($bytes > 1)
            {
                $bytes = $bytes . ' bytes';
            }
            elseif ($bytes == 1)
            {
                $bytes = $bytes . ' byte';
            }
            else
            {
                $bytes = '0 bytes';
            }

            return $bytes;
        }
        /* Резервное Копирование - Удаление */
        public function mk_file_manager_single_backup_remove_callback(){
            $nonce = $_POST['nonce'];
            if(current_user_can('manage_options') && wp_verify_nonce( $nonce, 'wpfmbackupremove' )) {
            global $wpdb;
            $fmdb = $wpdb->prefix.'wpfm_backup';
            $upload_dir = wp_upload_dir();
            $backup_dirname = $upload_dir['basedir'].'/wp-file-manager-pro/fm_backup/';
            $bkpId = (int) $_POST['id'];
            $isRemoved = false;
            if(isset($bkpId)) {
                    $fmbkp = $wpdb->get_row('select * from '.$fmdb.' where id = "'.$bkpId.'"');
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-db.sql.gz')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-db.sql.gz');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-others.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-others.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-plugins.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-plugins.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-themes.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-themes.zip');
                    }
                    if(file_exists($backup_dirname.$fmbkp->backup_name.'-uploads.zip')) {
                        unlink($backup_dirname.$fmbkp->backup_name.'-uploads.zip');
                    }
                    // удаление из БД
                    $wpdb->delete($fmdb, array('id' => $bkpId));
                    $isRemoved = true;
            }
            if($isRemoved) {
                echo 'Резервная копия успешно удалена!';
            } else {
                echo 'Невозможно удалить резервную копию!';
            }
            die;
        }
        }
        /* Фукнция Резервного Копирования Ajax */
        public function mk_file_manager_backup_callback(){
            global $wpdb;
            $fmdb = $wpdb->prefix.'wpfm_backup';
            $date = date('Y-m-d H:i:s');
            $file_number = 'backup_'.date('Y_m_d_H_i_s-').rand(0,9999);
            $nonce = $_POST['nonce'];
            $type = $_POST['type'];
            $database = $_POST['database'];
            $files = $_POST['files'];
            $plugins = $_POST['plugins'];
            $themes = $_POST['themes'];
            $uploads = $_POST['uploads'];
            $others = $_POST['others'];
            $bkpid = isset($_POST['bkpid']) ? $_POST['bkpid'] : '';
            if($database == 'false' && $files == 'false' && $bkpid == '') {
                echo json_encode(array('step' => '0', 'database' => 'false','files' => 'false','plugins' => 'false','themes' => 'false', 'uploads'=> 'false', 'others' => 'false', 'bkpid' => '0', 'msg' => '<span class="fm_console_error">Nothing selected for backup</span>'));
                die;
            }
            if($bkpid == '') {
                $wpdb->insert(
                    $fmdb,
                    array(
                        'backup_name' => $file_number,
                        'backup_date' => $date
                    ),
                    array(
                        '%s',
                        '%s'
                    )
                );
                $id = $wpdb->insert_id;
            } else {
                $id = $bkpid;
            }
            if ( ! wp_verify_nonce( $nonce, 'wpfmbackup' ) ) {
                echo json_encode(array('step' => 0, 'msg' => '<span class="fm_console_error">Вопрос безопасности.</span>'));
            } else {
              $fileName = $wpdb->get_row("select * from ".$fmdb." where id='".$id."'");
                // База данных
                if($database == 'true') {
                    include('classes/db-backup.php');
                    $backupDatabase = new Backup_Database($fileName->backup_name);
                    $result = $backupDatabase->backupTables(TABLES);
                    if($result == '1'){
                        echo json_encode(array('step' => 1, 'database' => 'false','files' => $files,'plugins' => $plugins,'themes' => $themes, 'uploads'=> $uploads, 'others' => $others,'bkpid' => $id,'msg' => '<span class="fm_console_success">Резервное копирование Базы данных успешно.</span>'));
                        die;
                    } else {
                        echo json_encode(array('step' => 1, 'database' => 'false','files' => $files,'plugins' => $plugins,'themes' => $themes, 'uploads'=> $uploads, 'others' => $others,'bkpid' => $id, 'msg' => '<span class="fm_console_error">Невозможно создать резервную копию Базы данных.</span>'));
                        die;
                    }
                }
                else if($files == 'true') {
                    include('classes/files-backup.php');
                    $upload_dir = wp_upload_dir();
                    $backup_dirname = $upload_dir['basedir'].'/wp-file-manager-pro/fm_backup';
                    $filesBackup = new aton_file_manager_files_backup();
                     // Плагины
                     if($plugins == 'true') {
                        $plugin_dir = WP_PLUGIN_DIR;
                        $backup_plugins = $filesBackup->zipData( $plugin_dir,$backup_dirname.'/'.$fileName->backup_name.'-plugins.zip');
                        if($backup_plugins) {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => $themes, 'uploads'=> $uploads, 'others' => $others,'bkpid' => $id, 'msg' => '<span class="fm_console_success">Резервное копирование Плагинов успешно.</span>'));
                            die;
                        } else {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => $themes, 'uploads'=> $uploads, 'others' => $others, 'bkpid' => $id, 'msg' => '<span class="fm_console_error">Невозможно создать резервную копию Плагинов.</span>'));
                            die;
                        }
                     }
                     // Темы
                     else if($themes == 'true') {
                        $themes_dir = get_theme_root();
                        $backup_themes = $filesBackup->zipData( $themes_dir,$backup_dirname.'/'.$fileName->backup_name.'-themes.zip');
                        if($backup_themes) {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => 'false', 'uploads'=> $uploads, 'others' => $others, 'bkpid' => $id, 'msg' => '<span class="fm_console_success">Резервное копирование Темы успешно.</span>'));
                            die;
                        } else {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => $themes, 'uploads'=> $uploads, 'others' => $others, 'bkpid' => $id, 'msg' => '<span class="fm_console_error">Невозможно создать резервную копию Темы.</span>'));
                            die;
                        }
                     }
                     // Загрузки
                     else if($uploads == 'true') {
                        $wpfm_upload_dir = wp_upload_dir();
                        $uploads_dir = $wpfm_upload_dir['basedir'];
                        $backup_uploads = $filesBackup->zipData( $uploads_dir,$backup_dirname.'/'.$fileName->backup_name.'-uploads.zip');
                        if($backup_uploads) {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => 'false', 'uploads'=> 'false', 'others' => $others, 'bkpid' => $id, 'msg' => '<span class="fm_console_success">Резервное копирование Загрузок успешно.</span>'));
                            die;
                        } else {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => 'false', 'uploads'=> 'false', 'others' => $others, 'bkpid' => $id, 'msg' => '<span class="fm_console_error">Невозможно создать резервную копию Загрузок.</span>'));
                            die;
                        }
                     }
                     // Другое
                     else if($others == 'true') {
                        $others_dir = WP_CONTENT_DIR;
                        $backup_others = $filesBackup->zipOther( $others_dir,$backup_dirname.'/'.$fileName->backup_name.'-others.zip');
                        if($backup_others) {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => 'false', 'uploads'=> 'false', 'others' => 'false', 'bkpid' => $id, 'msg' => '<span class="fm_console_success">Резервное копирование Другого успешно.</span>'));
                            die;
                        } else {
                            echo json_encode(array('step' => 1, 'database' => 'false','files' => 'true','plugins' => 'false','themes' => 'false', 'uploads'=> 'false', 'others' => 'false', 'bkpid' => $id, 'msg' => '<span class="fm_console_error">Невозможно создать резервную копию Другого.</span>'));

                        }
                     } else {
                        echo json_encode(array('step' => 0, 'database' => 'false', 'files' => 'false','plugins' => 'false','themes' => 'false','uploads'=> 'false','others' => 'false', 'bkpid' => $id, 'msg' => '<span class="fm_console_success">Все готово</span>'));
                        die;
                     }
                } else {
                 echo json_encode(array('step' => 0, 'database' => 'false', 'files' => 'false','plugins' => 'false','themes' => 'false','uploads'=> 'false','others' => 'false','bkpid' => $id, 'msg' => '<span class="fm_console_success">Все готово</span>'));
                }
            }
            die;
        }

        /* Верификация Email */
        public function mk_filemanager_verify_email_callback()
        {
            $current_user = wp_get_current_user();
            $nonce = $_REQUEST['vle_nonce'];
            if (wp_verify_nonce($nonce, 'verify-filemanager-email')) {
                $action = sanitize_text_field($_POST['todo']);
                $lokhal_email = sanitize_text_field($_POST['lokhal_email']);
                $lokhal_fname = sanitize_text_field($_POST['lokhal_fname']);
                $lokhal_lname = sanitize_text_field($_POST['lokhal_lname']);
                // case - 1 - закрытие
                if ($action == 'cancel') {
                    set_transient('filemanager_cancel_lk_popup_'.$current_user->ID, 'filemanager_cancel_lk_popup_'.$current_user->ID, 60 * 60 * 24 * 30);
                    update_option('filemanager_email_verified_'.$current_user->ID, 'yes');
                } elseif ($action == 'verify') {
                    $engagement = '75';
                    update_option('filemanager_email_address_'.$current_user->ID, $lokhal_email);
                    update_option('verify_filemanager_fname_'.$current_user->ID, $lokhal_fname);
                    update_option('verify_filemanager_lname_'.$current_user->ID, $lokhal_lname);
                    update_option('filemanager_email_verified_'.$current_user->ID, 'yes');
                    /* Отправить код на электронную почту */
                    $subject = 'Верификация Email';
                    $message = "
					<html>
					<head>
					<title>Верификация Email</title>
					</head>
					<body>
					<p>Спасибо, что подписались! Просто нажмите на ссылку ниже, чтобы проверить свою электронную почту, и мы будем держать вас в курсе последних версий!</p>
					<p><a href='".admin_url('admin-ajax.php?action=verify_filemanager_email&token='.md5($lokhal_email))."'>Нажмите здесь, чтобы проверить</a></p>
					</body>
					</html>
					";
                    // Always set content-type when sending HTML email
                    $headers = 'MIME-Version: 1.0'."\r\n";
                    $headers .= 'Content-type:text/html;charset=UTF-8'."\r\n";
                    $headers .= 'From: noreply@ikon.digital'."\r\n";
                    $mail = mail($lokhal_email, $subject, $message, $headers);
                    $data = $this->verify_on_server($lokhal_email, $lokhal_fname, $lokhal_lname, $engagement, 'verify', '0');
                    if ($mail) {
                        echo '1';
                    } else {
                        echo '2';
                    }
                }
            } else {
                echo 'Nonce';
            }
            die;
        }

        /* Верификация Email */
        public function verify_filemanager_email_callback()
        {
            $email = sanitize_text_field($_GET['token']);
            $current_user = wp_get_current_user();
            $lokhal_email_address = md5(get_option('filemanager_email_address_'.$current_user->ID));
            if ($email == $lokhal_email_address) {
                $this->verify_on_server(get_option('filemanager_email_address_'.$current_user->ID), get_option('verify_filemanager_fname_'.$current_user->ID), get_option('verify_filemanager_lname_'.$current_user->ID), '100', 'verified', '1');
                update_option('filemanager_email_verified_'.$current_user->ID, 'yes');
                echo '<p>Электронная Почта проверена Успешно. Перенаправление, пожалуйста подождите.</p>';
                echo '<script>';
                echo 'setTimeout(function(){window.location.href="https://filemanagerpro.io?utm_redirect=wp" }, 2000);';
                echo '</script>';
            }
            die;
        }

        /* Отправка Данных На Сервер */
        public function verify_on_server($email, $fname, $lname, $engagement, $todo, $verified)
        {
            global $wpdb, $wp_version;
            if (get_bloginfo('version') < '3.4') {
                $theme_data = get_theme_data(get_stylesheet_directory().'/style.css');
                $theme = $theme_data['Name'].' '.$theme_data['Version'];
            } else {
                $theme_data = wp_get_theme();
                $theme = $theme_data->Name.' '.$theme_data->Version;
            }

            // Определение хостинг-провайдера
            $host = false;
            if (defined('WPE_APIKEY')) {
                $host = 'WP Engine';
            } elseif (defined('PAGELYBIN')) {
                $host = 'Pagely';
            }
            $mysql_ver = @mysqli_get_server_info($wpdb->dbh);
            $id = get_option('page_on_front');
            $info = array(
                         'email' => $email,
                         'first_name' => $fname,
                         'last_name' => $lname,
                         'engagement' => $engagement,
                         'SITE_URL' => site_url(),
                         'PHP_version' => phpversion(),
                         'upload_max_filesize' => ini_get('upload_max_filesize'),
                         'post_max_size' => ini_get('post_max_size'),
                         'memory_limit' => ini_get('memory_limit'),
                         'max_execution_time' => ini_get('max_execution_time'),
                         'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
                         'wp_version' => $wp_version,
                         'plugin' => 'aton file manager',
                         'nonce' => 'um235gt9duqwghndewi87s34dhg',
                         'todo' => $todo,
                         'verified' => $verified,
                );
            $str = http_build_query($info);
            $args = array(
                'body' => $str,
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'cookies' => array(),
            );

            $response = wp_remote_post($this->SERVER, $args);

            return $response;
        }

        /* Файловый менеджер текстового домена */
        public function filemanager_load_text_domain()
        {
            $domain = dirname(plugin_basename(__FILE__));
            $locale = apply_filters('plugin_locale', get_locale(), $domain);
            load_textdomain($domain, trailingslashit(WP_LANG_DIR).'plugins'.'/'.$domain.'-'.$locale.'.mo');
            load_plugin_textdomain($domain, false, basename(dirname(__FILE__)).'/languages/');
        }

        /* Меню страниц */
        public function ffm_menu_page()
        {
            add_menu_page(
            __('Файловый менеджер АТОН', 'wp-file-manager'),
            __('Файловый менеджер АТОН', 'wp-file-manager'),
            'manage_options',
            'wp_file_manager',
            array(&$this, 'ffm_settings_callback'),
            plugins_url('images/wp_file_manager.png', __FILE__)
            );
            /* Только для админов */
            add_submenu_page('wp_file_manager', __('Резервное Копирование / Восстановление', 'wp-file-manager'), __('Резервное Копирование / Восстановление', 'wp-file-manager'), 'manage_options', 'wpfm-backup', array(&$this, 'wp_file_manager_backup'));
            /* Только для админов */
            add_submenu_page('wp_file_manager', __('Персональные настройки', 'wp-file-manager'), __('Персональные настройки', 'wp-file-manager'), 'manage_options', 'wp_file_manager_root', array(&$this, 'wp_file_manager_root'));
            /* Только для админов*/
            add_submenu_page('wp_file_manager', __('Системные свойства', 'wp-file-manager'), __('Системные свойства', 'wp-file-manager'), 'manage_options', 'wp_file_manager_properties', array(&$this, 'wp_file_manager_properties'));

        }
        /* Главная роль */
        public function ffm_settings_callback()
        {
            if (is_admin()):
             include 'lib/wpfilemanager.php';
            endif;
        }

        /*  Бэкап */
        public function wp_file_manager_backup() {
            if (is_admin()):
                include 'inc/backup.php';
            endif;
        }

        /* Системные свойства */
        public function wp_file_manager_properties()
        {
            if (is_admin()):
             include 'inc/system_properties.php';
            endif;
        }

        /* Root */
        public function wp_file_manager_root()
        {
            if (is_admin()):
             include 'inc/root.php';
            endif;
        }

         /* Административные инструменты */
         public function ffm_admin_things()
         {
             $getPage = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
             $allowedPages = array(
                                       'wp_file_manager',
                                       );
             // Язык
            $lang = isset($_GET['lang']) && !empty($_GET['lang']) ? sanitize_text_field($_GET['lang']) : '';
             if (!empty($getPage) && in_array($getPage, $allowedPages)):
                $fm_nonce = wp_create_nonce('wp-file-manager');
                $wp_fm_lang = get_transient('wp_fm_lang');
                $wp_fm_theme = get_transient('wp_fm_theme');
                $opt = get_option('wp_file_manager_settings');

                 wp_enqueue_style('jquery-ui', plugins_url('css/jquery-ui.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_commands', plugins_url('lib/css/commands.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_common', plugins_url('lib/css/common.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_contextmenu', plugins_url('lib/css/contextmenu.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_cwd', plugins_url('lib/css/cwd.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_dialog', plugins_url('lib/css/dialog.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_fonts', plugins_url('lib/css/fonts.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_navbar', plugins_url('lib/css/navbar.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_places', plugins_url('lib/css/places.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_quicklook', plugins_url('lib/css/quicklook.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_statusbar', plugins_url('lib/css/statusbar.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('theme', plugins_url('lib/css/theme.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_toast', plugins_url('lib/css/toast.css', __FILE__), '', $this->ver);
                 wp_enqueue_style('fm_toolbar', plugins_url('lib/css/toolbar.css', __FILE__), '', $this->ver);
                 wp_enqueue_script('jquery');
                 wp_enqueue_script('fm_jquery_js', plugins_url('js/top.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_jquery_ui', plugins_url('lib/jquery/jquery-ui-1.12.0.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_elFinder', plugins_url('lib/js/elFinder.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_version', plugins_url('lib/js/elFinder.version.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_jquery_elfinder', plugins_url('lib/js/jquery.elfinder.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_mimetypes', plugins_url('lib/js/elFinder.mimetypes.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_options', plugins_url('lib/js/elFinder.options.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_options_netmount', plugins_url('lib/js/elFinder.options.netmount.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_history', plugins_url('lib/js/elFinder.history.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_command', plugins_url('lib/js/elFinder.command.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_elFinder_resources', plugins_url('lib/js/elFinder.resources.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_dialogelfinder', plugins_url('lib/js/jquery.dialogelfinder.js', __FILE__), '', $this->ver);

                 if (!empty($lang)) {
                    set_transient('wp_fm_lang', $lang, 60 * 60 * 720);
                    wp_enqueue_script('fm_lang', plugins_url('lib/js/i18n/elfinder.'.$lang.'.js', __FILE__), '', $this->ver);

                    } elseif (false !== ($wp_fm_lang = get_transient('wp_fm_lang'))) {
                            wp_enqueue_script('fm_lang', plugins_url('lib/js/i18n/elfinder.'.$wp_fm_lang.'.js', __FILE__), '', $this->ver);
                    } else {
                        wp_enqueue_script('fm_lang', plugins_url('lib/js/i18n/elfinder.en.js', __FILE__), '', $this->ver);
                    }
                 wp_enqueue_script('fm_ui_button', plugins_url('lib/js/ui/button.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_contextmenu', plugins_url('lib/js/ui/contextmenu.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_cwd', plugins_url('lib/js/ui/cwd.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_dialog', plugins_url('lib/js/ui/dialog.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_fullscreenbutton', plugins_url('lib/js/ui/fullscreenbutton.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_navbar', plugins_url('lib/js/ui/navbar.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_navdock', plugins_url('lib/js/ui/navdock.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_overlay', plugins_url('lib/js/ui/overlay.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_panel', plugins_url('lib/js/ui/panel.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_ui_path', plugins_url('lib/js/ui/path.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_ui_searchbutton', plugins_url('lib/js/ui/searchbutton.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_sortbutton', plugins_url('lib/js/ui/sortbutton.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_stat', plugins_url('lib/js/ui/stat.js', __FILE__), '', $this->ver);


                 wp_enqueue_script('fm_ui_toast', plugins_url('lib/js/ui/toast.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_toolbar', plugins_url('lib/js/ui/toolbar.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_tree', plugins_url('lib/js/ui/tree.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_uploadButton', plugins_url('lib/js/ui/uploadButton.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_ui_viewbutton', plugins_url('lib/js/ui/viewbutton.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_ui_workzone', plugins_url('lib/js/ui/workzone.js', __FILE__), '', $this->ver);



                 wp_enqueue_script('fm_command_archive', plugins_url('lib/js/commands/archive.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_back', plugins_url('lib/js/commands/back.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_chmod', plugins_url('lib/js/commands/chmod.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_colwidth', plugins_url('lib/js/commands/colwidth.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_copy', plugins_url('lib/js/commands/copy.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_cut', plugins_url('lib/js/commands/cut.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_download', plugins_url('lib/js/commands/download.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_duplicate', plugins_url('lib/js/commands/duplicate.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_edit', plugins_url('lib/js/commands/edit.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_empty', plugins_url('lib/js/commands/empty.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_extract', plugins_url('lib/js/commands/extract.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_forward', plugins_url('lib/js/commands/forward.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_fullscreen', plugins_url('lib/js/commands/fullscreen.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_getfile', plugins_url('lib/js/commands/getfile.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_help', plugins_url('lib/js/commands/help.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_hidden', plugins_url('lib/js/commands/hidden.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_hide', plugins_url('lib/js/commands/hide.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_home', plugins_url('lib/js/commands/home.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_info', plugins_url('lib/js/commands/info.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_mkdir', plugins_url('lib/js/commands/mkdir.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_mkfile', plugins_url('lib/js/commands/mkfile.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_netmount', plugins_url('lib/js/commands/netmount.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_open', plugins_url('lib/js/commands/open.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_opendir', plugins_url('lib/js/commands/opendir.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_opennew', plugins_url('lib/js/commands/opennew.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_paste', plugins_url('lib/js/commands/paste.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_places', plugins_url('lib/js/commands/places.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_quicklook', plugins_url('lib/js/commands/quicklook.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_quicklook_plugins', plugins_url('lib/js/commands/quicklook.plugins.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_reload', plugins_url('lib/js/commands/reload.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_rename', plugins_url('lib/js/commands/rename.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_resize', plugins_url('lib/js/commands/resize.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_restore', plugins_url('lib/js/commands/restore.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_rm', plugins_url('lib/js/commands/rm.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_search', plugins_url('lib/js/commands/search.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_selectall', plugins_url('lib/js/commands/selectall.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_selectinvert', plugins_url('lib/js/commands/selectinvert.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_selectnone', plugins_url('lib/js/commands/selectnone.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_sort', plugins_url('lib/js/commands/sort.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_command_undo', plugins_url('lib/js/commands/undo.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_up', plugins_url('lib/js/commands/up.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_upload', plugins_url('lib/js/commands/upload.js', __FILE__), '', $this->ver);
                 wp_enqueue_script('fm_command_view', plugins_url('lib/js/commands/view.js', __FILE__), '', $this->ver);

                 wp_enqueue_script('fm_quicklook_googledocs', plugins_url('lib/js/extras/quicklook.googledocs.js', __FILE__), '', $this->ver);

                 // code mirror
                wp_enqueue_script('fm-codemirror-js', plugins_url('lib/codemirror/lib/codemirror.js', __FILE__), '', $this->ver);

                wp_enqueue_style('fm-codemirror', plugins_url('lib/codemirror/lib/codemirror.css', __FILE__), '', $this->ver);

                wp_enqueue_style('fm-3024-day', plugins_url('lib/codemirror/theme/3024-day.css', __FILE__), '', $this->ver);
                // Пользовательский интерфейс Файлового менеджера
                wp_register_script( "file_manager_free_shortcode_admin", plugins_url('js/file_manager_free_shortcode_admin.js',  __FILE__ ), array(), rand(0,9999) );
                 wp_localize_script( 'file_manager_free_shortcode_admin', 'fmfparams', array(
                     'ajaxurl' => admin_url('admin-ajax.php'),
                     'nonce' => $fm_nonce,
                     'lang' => isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : (($wp_fm_lang !== false) ? $wp_fm_lang : 'en'),
                     'fm_enable_media_upload' => (isset($opt['fm_enable_media_upload']) && $opt['fm_enable_media_upload'] == '1') ? '1' : '0',
                     )
                 );
                 wp_enqueue_script( 'file_manager_free_shortcode_admin' );

             $theme = isset($_GET['theme']) && !empty($_GET['theme']) ? sanitize_text_field($_GET['theme']) : '';
             // Новая тема
             if (!empty($theme)) {
                 delete_transient('wp_fm_theme');
                 set_transient('wp_fm_theme', $theme, 60 * 60 * 720);
                 if ($theme != 'default') {
                     wp_enqueue_style('theme-latest', plugins_url('lib/themes/'.$theme.'/css/theme.css', __FILE__), '', $this->ver);
                 }
             } elseif (false !== ($wp_fm_theme = get_transient('wp_fm_theme'))) {
                 if ($wp_fm_theme != 'default') {
                     wp_enqueue_style('theme-latest', plugins_url('lib/themes/'.$wp_fm_theme.'/css/theme.css', __FILE__), '', $this->ver);
                 }
             } else {
             }
             endif;
         }


        /* Обработчик запросов Ajax.
        Запуск Файлового Менеджера*/
        public function mk_file_folder_manager_action_callback()
        {
            $path = ABSPATH;
            $settings = get_option('wp_file_manager_settings');
            if (isset($settings['public_path']) && !empty($settings['public_path'])) {
                $path = $settings['public_path'];
            }
            $mk_restrictions = array();
            $mk_restrictions[] = array(
                                  'pattern' => '/.tmb/',
                                   'read' => false,
                                   'write' => false,
                                   'hidden' => true,
                                   'locked' => false,
                                );
            $mk_restrictions[] = array(
                                  'pattern' => '/.quarantine/',
                                   'read' => false,
                                   'write' => false,
                                   'hidden' => true,
                                   'locked' => false,
                                );
            $nonce = $_REQUEST['_wpnonce'];
            if (wp_verify_nonce($nonce, 'wp-file-manager')) {
                require 'lib/php/autoload.php';
                if (isset($settings['fm_enable_trash']) && $settings['fm_enable_trash'] == '1') {
                    $mkTrash = array(
                            'id' => '1',
                            'driver' => 'Trash',
                            'path' => WP_FILE_MANAGER_PATH.'lib/files/.trash/',
                            'tmbURL' => site_url().'/lib/files/.trash/.tmb/',
                            'winHashFix' => DIRECTORY_SEPARATOR !== '/',
                            'uploadDeny' => array(''),
                            'uploadAllow' => array(''),
                            'uploadOrder' => array('deny', 'allow'),
                            'accessControl' => 'access',
                            'attributes' => $mk_restrictions,
                        );
                    $mkTrashHash = 't1_Lw';
                } else {
                    $mkTrash = array();
                    $mkTrashHash = '';
                }
                $opts = array(
                       'debug' => false,
                       'roots' => array(
                        array(
                            'driver' => 'LocalFileSystem',
                            'path' => $path,
                            'URL' => site_url(),
                            'trashHash' => $mkTrashHash,
                            'winHashFix' => DIRECTORY_SEPARATOR !== '/',
                            'uploadDeny' => array(),
                            'uploadAllow' => array('image', 'text/plain'),
                            'uploadOrder' => array('deny', 'allow'),
                            'accessControl' => 'access',
                            'acceptedName' => 'validName',
                            'disabled' => array('help', 'preference'),
                            'attributes' => $mk_restrictions,
                        ),
                        $mkTrash,
                    ),
                );
                // запуск elFinder
                $connector = new elFinderConnector(new elFinder($opts));
                $connector->run();
            }
            die;
        }

        /* разрешения */
        public function permissions()
        {
            $permissions = 'manage_options';

            return $permissions;
        }

        /* Открыть Справочную Службу */
        public function load_help_desk()
        {
            $mkcontent = '';
            $mkcontent .= '<div class="wfmrs">';
            $mkcontent .= '<div class="l_wfmrs">';
            $mkcontent .= '';
            $mkcontent .= '</div>';
            $mkcontent .= '<div class="r_wfmrs">';
            $mkcontent .= '<a class="close_fm_help fm_close_btn" href="javascript:void(0)" data-ct="rate_later" title="close">X</a><strong>WP File Manager</strong><p>We love and care about you. Our team is putting maximum efforts to provide you the best functionalities. It would be highly appreciable if you could spend a couple of seconds to give a Nice Review to the plugin to appreciate our efforts. So we can work hard to provide new features regularly :)</p><a class="close_fm_help fm_close_btn_1" href="javascript:void(0)" data-ct="rate_later" title="Remind me later">Later</a> <a class="close_fm_help fm_close_btn_2" href="https://wordpress.org/support/plugin/wp-file-manager/reviews/?filter=5" data-ct="rate_now" title="Rate us now" target="_blank">Rate Us</a> <a class="close_fm_help fm_close_btn_3" href="javascript:void(0)" data-ct="rate_never" title="Not interested">Never</a>';
            $mkcontent .= '</div></div>';
            if (false === ($mk_fm_close_fm_help_c_fm = get_option('mk_fm_close_fm_help_c_fm'))) {
                echo apply_filters('the_content', $mkcontent);
            }
        }

        /* Закрыть Справочную Службу */
        public function mk_fm_close_fm_help()
        {
            $what_to_do = sanitize_text_field($_POST['what_to_do']);
            $expire_time = 15;
            if ($what_to_do == 'rate_now' || $what_to_do == 'rate_never') {
                $expire_time = 365;
            } elseif ($what_to_do == 'rate_later') {
                $expire_time = 15;
            }
            if (false === ($mk_fm_close_fm_help_c_fm = get_option('mk_fm_close_fm_help_c_fm'))) {
                $set = update_option('mk_fm_close_fm_help_c_fm', 'done');
                if ($set) {
                    echo 'ok';
                } else {
                    echo 'oh';
                }
            } else {
                echo 'ac';
            }
            die;
        }

        /* Загрузка Пользовательских Активов */
        public function load_custom_assets()
        {
            echo '<script src="'.plugins_url('js/fm_script.js', __FILE__).'"></script>';
            echo "<link rel='stylesheet' href='".plugins_url('css/fm_script.css', __FILE__)."' type='text/css' media='all' />
		   ";
        }

        /* custom_css */
        public function custom_css()
        {
            echo "<link rel='stylesheet' href='".plugins_url('css/fm_custom.css', __FILE__)."' type='text/css' media='all' />
		   ";
        }

        /* Язык */
        public function fm_languages()
        {
            $langs = array(
                          'Russian (Русский)' => 'ru',
                          );

            return $langs;
        }

        /* получить все темы */
        public function get_themes()
        {
            $dir = dirname(__FILE__).'/lib/themes';
            $theme_files = array_diff(scandir($dir), array('..', '.'));

            return $theme_files;
        }

        /* Сообщение Об Успехе */
        public function success($msg)
        {
            _e('<div class="updated settings-error notice is-dismissible" id="setting-error-settings_updated">
<p><strong>'.$msg.'</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">Закрыть это уведомление.</span></button></div>', 'te-editor');
        }

        /* Сообщение Об Ошибке */
        public function error($msg)
        {
            _e('<div class="error settings-error notice is-dismissible" id="setting-error-settings_updated">
<p><strong>'.$msg.'</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">Закрыть это уведомление.</span></button></div>', 'te-editor');
        }

        /* Перенаправлять */
        public function redirect($url)
        {
            echo '<script>';
            echo 'window.location.href="'.$url.'"';
            echo '</script>';
        }
        /* Admin - Активы */
        public function fm_custom_assets()
        {
            wp_enqueue_style('fm_custom_style', plugins_url('/css/fm_custom_style.css', __FILE__));
        }
        /* Загрузка Мультимедии */
        public function mk_file_folder_manager_media_upload() {
            $nonce = $_REQUEST['_wpnonce'];
            if (current_user_can('manage_options') && wp_verify_nonce($nonce, 'wp-file-manager')) {
                $uploadedfiles = isset($_POST['uploadefiles']) ? $_POST['uploadefiles'] : '';
                if(!empty($uploadedfiles)) {
                    $files = '';
                    $fileCount = 1;
                    foreach($uploadedfiles as $uploadedfile) {
                    /* Начало - загрузка изображения в Media Lib */
                    $this->upload_to_media_library($uploadedfile);
                    /* Конец - загрузки изображения в to Media Lib */
                    }
                }
            }
			  die;
        }
       /* Загрузка изображений в Media Library */
       public function upload_to_media_library($image_url) {
            $allowed_exts = array('jpg','jpe',
                                  'jpeg','gif',
                                  'png','svg',
                                  'pdf','zip',
                                  'ico','pdf',
                                  'doc','docx',
                                  'ppt','pptx',
                                  'pps','ppsx',
                                  'odt','xls',
                                  'xlsx','psd',
                                  'mp3','m4a',
                                  'ogg','wav',
                                  'mp4','m4v',
                                  'mov','wmv',
                                  'avi','mpg',
                                  'ogv','3gp',
                                  '3g2'
                                );
            $url = $image_url;
            preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png|pdf|zip|ico|pdf|doc|docx|ppt|pptx|pps|ppsx|odt|xls|xlsx|psd|mp3|m4a|ogg|wav|mp4|m4v|mov|wmv|avi|mpg|ogv|3gp|3g2)/i', $url, $matches);
             if(in_array($matches[1], $allowed_exts)) {
			// Нужно ли требовать эти файлы
					if ( !function_exists('media_handle_upload') ) {
						require_once(ABSPATH . "wp-admin" . '/includes/image.php');
						require_once(ABSPATH . "wp-admin" . '/includes/file.php');
						require_once(ABSPATH . "wp-admin" . '/includes/media.php');
					}

					$tmp = download_url( $url );
					$post_id = 0;
					$desc = "";
					$file_array = array();
					$file_array['name'] = basename($matches[0]);
					// Если ошибка сохраняется, разъединить связь
					if ( is_wp_error( $tmp ) ) {
						@unlink($file_array['tmp_name']);
						$file_array['tmp_name'] = '';
					} else {
						$file_array['tmp_name'] = $tmp;
					}
					$id = media_handle_sideload( $file_array, $post_id, $desc );
					if ( is_wp_error($id) ) {
						@unlink($file_array['tmp_name']);
						return $id;
                    }
            }
		 }

    }
    $filemanager = new mk_file_folder_manager();
    global $filemanager;
    /* конец класс */
endif;
