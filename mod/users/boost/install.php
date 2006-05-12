<?php
  /**
   * boost install file for users
   *
   * @author Matthew McNaney <mcnaney at gmail dot com>
   * @version $Id$
   */


function users_install(&$content)
{
    PHPWS_Core::initModClass('users', 'Users.php');
    PHPWS_Core::initModClass('users', 'Action.php');
    PHPWS_Core::configRequireOnce('users', 'config.php');

    if (isset($_REQUEST['module']) && $_REQUEST['module'] == 'branch') {
        $source_db = DB::connect(PHPWS_DSN);
        $result = $source_db->getAssoc('select * from users where deity=1', NULL, NULL, DB_FETCHMODE_ASSOC);

        if (PEAR::isError($result)) {
            PHPWS_Error::log($result);
            $content[] = _('Could not access hub database.');
            return FALSE;
        }
        elseif (empty($result)) {
            $content[] = _('Could not find any hub deities.');
            return FALSE;
        } else {
            $db = & new PHPWS_DB('users');
            $pass_db = & new PHPWS_DB('user_authorization');

            foreach ($result as $deity) {
                unset($deity['id']);
                $sql = sprintf('select * from user_authorization where username=\'%s\'', $deity['username']);

                $login = $source_db->getRow($sql, NULL, DB_FETCHMODE_ASSOC);

                if (empty($login)) {
                    $content[] = _('Error: Missing login information for a deity user. Cannot copy to branch.');
                    continue;
                } elseif (PEAR::isError($login)) {
                    PHPWS_Error::log($login);
                    $content[] = _('Error: Missing login information for a deity user. Cannot copy to branch.');
                    continue;
                } else {
                    $pass_db->addValue($login);
                    $result = $pass_db->insert();
                    if (PEAR::isError($result)) {
                        PHPWS_Error::log($result);
                        $content[] = _('Unable to copy deity login to branch.');
                        continue;
                    }

                    $pass_db->reset();
                }

                $db->addValue($deity);
                $result = $db->insert();

                if (PEAR::isError($result)) {
                    PHPWS_Error::log($result);
                    $content[] = _('Unable to copy deity users to branch.');
                    return FALSE;
                }
                $db->reset();
            }
            $content[] = _('Deity users copied to branch.');
        }

        $db = & new PHPWS_DB('users_auth_scripts');
        $db->addValue('display_name', _('Local'));
        $db->addValue('filename', 'local.php');
        $db->insert();

        return TRUE;
    }

    $user = & new PHPWS_User;
    $content[] = '<hr />';

    
    if (isset($_POST['mod_title']) && $_POST['mod_title']=='users'){
        $result = User_Action::postUser($user);
        if (!is_array($result)){
            $user->setDeity(TRUE);
            $user->setActive(TRUE);
            $user->setApproved(TRUE);
            $user->setAuthorize(1);
            $result = $user->save();
            if (PEAR::isError($result)) {
                return $result;
            }

            PHPWS_Settings::set('users', array('site_contact' => $user->getEmail()));
            PHPWS_Settings::save('users');
            $content[] = _('User created successfully.');
            $content[] = _('User\'s email used as contact email address.');
            $db = & new PHPWS_DB('users_auth_scripts');
            $db->addValue('display_name', _('Local'));
            $db->addValue('filename', 'local.php');
            $db->insert();
        } else {
            $content[] = userForm($user, $result);
            return FALSE;
        }
    } else {
        $content[] = _('Please create a user to administrate the site.') . '<br />';
        $content[] = userForm($user);
        return FALSE;
    }

    return TRUE;
}


function userForm(&$user, $errors=NULL){
    PHPWS_Core::initCoreClass('Form.php');
    PHPWS_Core::initModClass('users', 'Form.php');

    translate('users');
    $form = & new PHPWS_Form;


    if (isset($_REQUEST['module'])) {
        $form->addHidden('module', $_REQUEST['module']);
    } else {
        $form->addHidden('step', 3);
    }

    $form->addHidden('mod_title', 'users');
    $form->addText('username', $user->getUsername());
    $form->addText('email', $user->getEmail());
    $form->addPassword('password1');
    $form->addPassword('password2');

    $form->setLabel('username', _('Username'));
    $form->setLabel('password1', _('Password'));
    $form->setLabel('email', _('Email'));

    $form->addSubmit('submit', _('Add User'));
  
    $template = $form->getTemplate();

    if (!empty($errors))
        foreach ($errors as $tag=>$message)
            $template[$tag] = $message;

    $result = PHPWS_Template::process($template, 'users', 'forms/userForm.tpl');

    $content[] = $result;
    return implode("\n", $content);
}


?>