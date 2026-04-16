<?php
if (!defined('ABSPATH')) exit;

// Přidá pole pro změnu loginu do editace uživatele
add_action('show_user_profile', 'pdk_show_username_change_field');
add_action('edit_user_profile', 'pdk_show_username_change_field');
function pdk_show_username_change_field($user) {
	
	
    if (!current_user_can('edit_users')) return;
    ?>
    <h2>Změna uživatelského jména</h2>
    <table class="form-table">
    <tr>
        <th><label for="pdk_new_user_login">Nové uživatelské jméno</label></th>
        <td>
            <input type="text" name="pdk_new_user_login" id="pdk_new_user_login"
                   value="<?php echo esc_attr($user->user_login); ?>" class="regular-text" />
            <br>
            <span class="description">Změna uživatelského jména může ovlivnit přihlašování uživatele.<br>
            Nedoporučuje se měnit pro správce webu.</span>
        </td>
    </tr>
    </table>
    <?php
}

// Uloží změnu loginu
add_action('personal_options_update', 'pdk_save_username_change');
add_action('edit_user_profile_update', 'pdk_save_username_change');
function pdk_save_username_change($user_id) {
    if (!current_user_can('edit_users')) return;
    if (!isset($_POST['pdk_new_user_login'])) return;
    $new_login = sanitize_user($_POST['pdk_new_user_login'], true);

    $user = get_userdata($user_id);
    if (!$user || $user->user_login === $new_login) return;

    if (empty($new_login)) return add_action('user_profile_update_errors', function($errors){
        $errors->add('pdk_login_empty', 'Uživatelské jméno nesmí být prázdné.');
    });

    if (username_exists($new_login)) return add_action('user_profile_update_errors', function($errors) use ($new_login){
        $errors->add('pdk_login_exists', 'Uživatelské jméno „' . esc_html($new_login) . '“ už existuje.');
    });

    global $wpdb;
    $result = $wpdb->update(
        $wpdb->users,
        ['user_login' => $new_login],
        ['ID' => $user_id]
    );
    if ($result !== false) {
        clean_user_cache($user_id);
        add_action('admin_notices', function(){
            echo '<div class="notice notice-success is-dismissible"><p>Uživatelské jméno bylo úspěšně změněno.</p></div>';
        });
    }
}

// Pokud chceš samostatnou záložku v menu:
function pdk_username_change_page() {
    echo '<div class="wrap"><h1>Změna uživatelského jména</h1><p>Pole pro změnu najdeš v detailu každého uživatele (Uživatelé &gt; Upravit).</p></div>';
}