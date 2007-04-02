<?php

  /**
   * @version $Id$
   * @author Matthew McNaney <mcnaney at gmail dot com>
   */

PHPWS_Core::requireConfig('filecabinet');
PHPWS_Core::initModClass('filecabinet', 'Image.php');

class FC_Image_Manager {
    var $image      = null;
    var $itemname   = null;
    var $cabinet    = null;
    var $current    = 0;
    var $max_width  = 0;
    var $max_height = 0;
    var $max_size   = 0;

    function FC_Image_Manager($image_id=0)
    {
        $this->loadImage($image_id);
        $this->loadSettings();
    }

    // backward compatibility
    function setModule($foo)
    {
    }

    function setMaxSize($size)
    {
        $this->max_size = (int)$size;
    }

    function setItemName($itemname)
    {
        $this->itemname = $itemname;
    }

    function setMaxWidth($width)
    {
        $this->max_width = (int)$width;
    }


    function setMaxHeight($height)
    {
        $this->max_height = (int)$height;
    }


    function showImages($folder, $image_id=0)
    {
        if (!$folder->id) {
            return null;
        } else {
            if ($folder->loadFiles()) {
                $js_vars['itemname']  = $this->itemname;
                
                foreach ($folder->_files as $image) {
                    if ($image->id == $image_id) {
                        $tpl['SELECT'] = 'image-select';
                    } else {
                        $tpl['SELECT'] = '';
                    }

                    if ( ($this->max_width < $image->width) || ($this->max_height < $image->height) ) {
                        $tpl['THUMBNAIL'] = sprintf('<a href="#" onclick="oversized(%s, %s, %s); return false">%s</a>',
                                                    $image->id, $this->max_width, $this->max_height, $image->getThumbnail());
                    } else {
                        $tpl['THUMBNAIL'] = sprintf('<a href="#" onclick="pick_image(%s, \'%s\', \'%s\'); return false">%s</a>',
                                                    $image->id, $image->thumbnailPath(), addslashes($image->title), $image->getThumbnail());
                    }
                    $tpl['TITLE']     = $image->title;
                    $tpl['VIEW']      = $image->getJSView();
                    $tpl['ID']        = $image->id;
                    $tpl['WIDTH']     = $image->width;
                    $tpl['HEIGHT']    = $image->height;
                    $template['thumbnail-list'][] = $tpl;
                }

                $content =  PHPWS_Template::process($template, 'filecabinet', 'manager/pick.tpl');
            } else {
                $content = _('Folder empty.');
            }
        }
        
        return $content;
    }

    /**
     * Upload image form
     */
    function edit()
    {
        $this->cabinet->title = _('Upload image');
        $form = new PHPWS_Form;
        $form->addHidden('module', 'filecabinet');

        $form->addHidden('aop',      'post_image_upload');
        $form->addHidden('ms',        $this->max_size);
        $form->addHidden('mh',        $this->max_height);
        $form->addHidden('mw',        $this->max_width);
        $form->addHidden('folder_id', $this->cabinet->folder->id);

        // if 'im' is set, then we are inside the image manage interface
        // the post needs to be aware of that to respond correctly
        if (isset($_GET['im'])) {
            $form->addHidden('im', 1);
        }

        if ($this->image->id) {
            $form->addHidden('image_id', $this->image->id);
        }

        $form->addFile('file_name');
        $form->setSize('file_name', 30);
        $form->setMaxFileSize($this->max_size);

        $form->setLabel('file_name', _('Image location'));

        $form->addText('title', $this->image->title);
        $form->setSize('title', 40);
        $form->setLabel('title', _('Title'));

        $form->addText('alt', $this->image->alt);
        $form->setSize('alt', 40);
        $form->setLabel('alt', _('Alternate text'));

        $form->addTextArea('description', $this->image->description);
        $form->setLabel('description', _('Description'));


        if (!empty($this->image->id)) {
            $form->addSubmit(_('Update'));
        } else {
            $form->addSubmit(_('Upload'));
        }

        $template = $form->getTemplate();

        $template['CANCEL'] = sprintf('<input type="button" value="%s" onclick="javascript:window.close()" />', _('Cancel'));

        if ($this->image->id) {
            $template['CURRENT_IMAGE_LABEL'] = _('Current image');
            $template['CURRENT_IMAGE']       = $this->image->getJSView(TRUE);
        }
        $template['MAX_SIZE_LABEL']   = _('Maximum file size');
        $template['MAX_WIDTH_LABEL']  = _('Maximum width');
        $template['MAX_HEIGHT_LABEL'] = _('Maximum height');
        $template['MAX_SIZE']         = $this->max_size;
        $template['MAX_WIDTH']        = $this->max_width;
        $template['MAX_HEIGHT']       = $this->max_height;

        $this->cabinet->content = PHPWS_Template::process($template, 'filecabinet', 'image_edit.tpl');
    }


    function loadImage($image_id=0)
    {
        if (!$image_id && isset($_REQUEST['image_id'])) {
            $image_id = $_REQUEST['image_id'];
        }

        $this->image = new PHPWS_Image($image_id);
    }

    /**
     * From Cabinet::admin.
     * Error checks and posts the image upload
     */
    function postImageUpload()
    {
        // importPost in File_Common
        $result = $this->image->importPost('file_name');

        if (PEAR::isError($result)) {
            PHPWS_Error::log($result);
            $vars['timeout'] = '3';
            $vars['refresh'] = 0;
            $this->cabinet->content = _('An error occurred when trying to save your image.');
            javascript('close_refresh', $vars);
            return;
        } elseif ($result) {
            $result = $this->image->save();
            if (PEAR::isError($result)) {
                PHPWS_Error::log($result);
            }
            if (!isset($_POST['im'])) {
                javascript('close_refresh');
            } else {
                javascript('modules/filecabinet/refresh_manager', array('image_id'=>$this->image->id));
            }
        } else {
            $this->cabinet->message = $this->image->printErrors();
            $this->edit();
            return;
        }
    }

    function get()
    {
        translate('filecabinet');
        if ($this->image->id) {
            $label = $this->image->getThumbnail();
        } else {
            $label = $this->noImage();
        }

        $link_vars = $this->getSettings();
        $link_vars['aop']    = 'edit_image';
        $link_vars['current']   = $this->image->id;
   
        $vars['address'] = PHPWS_Text::linkAddress('filecabinet', $link_vars);
        $vars['width']   = 700;
        $vars['height']  = 600;
        $vars['label']   = $label;
        //        $vars['toolbar'] = 'yes';

        $tpl['IMAGE'] = javascript('open_window', $vars);
        
        $tpl['HIDDEN'] = sprintf('<input type="hidden" id="%s" name="%s" value="%s" />', $this->itemname . '_hidden_value', $this->itemname, $this->image->id);
        $tpl['ITEMNAME'] = $this->itemname;
        $tpl['CLEAR_IMAGE'] = $this->getClearLink();

        translate();
        return PHPWS_Template::process($tpl, 'filecabinet', 'manager/javascript.tpl');
    }

    function getSettings()
    {
        $vars['itemname']  = $this->itemname;
        $vars['ms']        = $this->max_size;
        $vars['mw']        = $this->max_width;
        $vars['mh']        = $this->max_height;

        return $vars;
    }

    function noImage()
    {
        $no_image = _('No image');
        return sprintf('<img src="%s" width="%s" height="%s" title="%s" alt="%s" />',
                             FC_NONE_IMAGE_SRC, 100, 
                             100, $no_image, $no_image);
    }

    function getClearLink()
    {
        $js_vars['src']      = FC_NONE_IMAGE_SRC;
        $js_vars['width']    = 100;
        $js_vars['height']   = 100;
        $js_vars['title']    = $js_vars['alt'] = _('No image');
        $js_vars['itemname'] = $this->itemname;
        $js_vars['label']    = _('Clear image');
        return javascript('modules/filecabinet/clear_image', $js_vars);
    }

    function loadSettings()
    {
        if (isset($_REQUEST['itemname'])) {
            $this->setItemname($_REQUEST['itemname']);
        }

        if (isset($_REQUEST['ms']) && $_REQUEST['ms'] > 1000) {
            $this->setMaxSize($_REQUEST['ms']);
        } else {
            $this->setMaxSize(PHPWS_Settings::get('filecabinet', 'max_image_size'));
        }

        if (isset($_REQUEST['mh']) && $_REQUEST['mh'] > 50) {
            $this->setMaxHeight($_REQUEST['mh']);
        } else {
            $this->setMaxHeight(PHPWS_Settings::get('filecabinet', 'max_image_height'));
        }

        if (isset($_REQUEST['mw']) && $_REQUEST['mw'] > 50) {
            $this->setMaxWidth($_REQUEST['mw']);
        } else {
            $this->setMaxWidth(PHPWS_Settings::get('filecabinet', 'max_image_width'));
        }
    }

    /**
     * This is the pop up menu where a user can pick an image.
     */
    function editImage()
    {
        if (isset($_GET['current'])) {
            $image = new PHPWS_Image($_GET['current']);
            $folder = new Folder($image->folder_id);
        }

        Layout::addStyle('filecabinet');
        $this->cabinet->title = _('Choose an image folder');

        // Needed only for image view popups.
        javascript('open_window');
        $js['itemname'] = $this->itemname;

        $js['failure_message'] = addslashes(_('Unable to resize image.'));
        $js['confirmation'] = sprintf(_('This image is larger than the %s x %s limit. Do you want to resize the image to fit?'),
                                      $this->max_width,
                                      $this->max_height);
        $js['authkey'] = Current_User::getAuthKey();

        javascript('modules/filecabinet/pick_image', $js);

        $db = new PHPWS_DB('folders');
        $db->addWhere('ftype', IMAGE_FOLDER);
        $db->addOrder('title');
        $folders = $db->getObjects('Folder');

        if (!empty($folders)) {
            foreach ($folders as $fldr) {
                $tpl['listrows'][] = $fldr->imageTags($this->max_width, $this->max_height);
            }
        }

        $address = PHPWS_Text::linkAddress('filecabinet', array('aop'=>'add_folder', 'ftype'=>IMAGE_FOLDER), true);
        $folder_window = sprintf("javascript:open_window('%s', %s, %s, 'new_folder'); return false", $address, 370, 420);
        $tpl['ADD_FOLDER'] = sprintf('<input id="add-folder" type="button" name="add_folder" value="%s" onclick="%s" />', _('Add folder'), $folder_window);

        $address = PHPWS_Text::linkAddress('filecabinet', array('aop'=>'upload_image_form', 'im'=>1, 'folder_id'=>$folder->id), true);
        $image_window = sprintf("javascript:open_window('%s', %s, %s, 'new_image'); return false", $address, 540, 460);
        $image_button = sprintf('<input id="add-image" type="button" name="add_image" value="%s" onclick="%s" />', _('Add image'), $image_window);

        $tpl['ADD_IMAGE'] = $image_button;

        if ($folder->id) {
            $show_images = $this->showImages($folder, $image->id);
            if (!empty($show_images)) {
                $tpl['IMAGE_LIST'] = &$show_images;
                $tpl['IMG_DISPLAY'] = 'visible';
            } else {
                $tpl['IMAGE_LIST'] = _('Bad folder id.');
            }
        } else {
            $tpl['IMG_DISPLAY'] = 'hidden';
            if (empty($folders)) {
                $tpl['IMAGE_LIST'] = _('Please create a new folder.');
            } else {
                $tpl['IMAGE_LIST'] = _('Please choose a new folder.');
            }
        }

        $this->cabinet->content = PHPWS_Template::process($tpl, 'filecabinet', 'image_folders.tpl');
    }

    function resizeImage()
    {
        $directory = $this->image->file_directory;
        $image_name = $this->image->file_name;

        $a_image = explode('.', $image_name);
        $ext = array_pop($a_image);
        $image_name = sprintf('%s_%sx%s.%s', implode('.', $a_image), $this->max_width, $this->max_height, $ext);
        $copy_dir = $directory . $image_name;

        if (is_file($copy_dir)) {
            // A copy already exists
            $image = new PHPWS_Image;
            $db = new PHPWS_DB('images');
            $db->addWhere('folder_id', $this->image->folder_id);
            $db->addWhere('file_name', $image_name);
            if ($db->loadObject($image)) {
                header('Content-type: text/xml');
                exit();
            } else {
                // image not in system, delete it and move on
                @unlink($copy_dir);
            }
        }

        if (!$this->max_width || !$this->max_height) {
            return null;
        }

        if ($this->image->resize($copy_dir, $this->max_width, $this->max_height)) {
            $image = new PHPWS_Image;
            $image->file_name      = $image_name;
            $image->file_directory = $directory;
            $image->folder_id      = $this->image->folder_id;
            $image->file_type      = $this->image->file_type;
            $image->title          = $this->image->title;
            $image->description    = $this->image->description;
            $image->alt            = $this->image->alt;
            $image->loadDimensions();
            $result = $image->save();
            $result = null;
            if (PEAR::isError($result)) {
                PHPWS_Error::log($result);
            } else {
                header('Content-type: text/xml');
                echo $image->xmlFormat();
            }
        }

        exit();
    }
}

?>