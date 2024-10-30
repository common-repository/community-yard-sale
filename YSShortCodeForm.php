<?php
/*
    "Community Yard Sale Plugin for WordPress" Copyright (C) 2011 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This file is part of Community Yard Sale Plugin for WordPress.

    Community Yard Sale Plugin for WordPress is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Community Yard Sale Plugin for WordPress is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Community Yard Sale Plugin for WordPress.
    If not, see <http://www.gnu.org/licenses/>.
*/

include_once('YSShortCodeLoader.php');
include_once('YSPlugin.php');

class YSShortCodeForm extends YSShortCodeLoader {

    /** @var YSPlugin */
    var $plugin;

    /** @var string */
    var $messageToUser = '';

    /** @var bool */
    var $showForm = true;

    /** @var array */
    var $data = array(
        'email' => '',
        'street' => '',
        'unit' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'listing' => '',
        'latlng' => ''
    );

    /** @var string */
    var $formJS;

    /** @var string */
    var $formId;

    /** @var string */
    var $mapId;

    /** @var string */
    var $lat = '39.01684968083152';

    /** @var string */
    var $lng = '-77.51137733459473';

    /** @var string */
    var $mapHeight = '500px';

    /** @var string */
    var $mapWidth = '100%';

    /** @var string */
    var $zoom = '14';

    /** @var array ($key => array($value)) options for city, state, zip */
    var $formOptions = array();

    /** @var array ($key => $value) defaults for city, state, zip */
    var $formDefaults = array(
        'city' => '',
        'state' => '',
        'zip' => ''
    );

    /** @var array ($key => $value) labels for city, state, zip */
    var $formLabels;

    /** @var array entries to be omitted from the form */
    var $omits = array();

    function __construct() {
        // Have to init in constructor due to "__" calls
        $this->formLabels = array(
                'email' => __('Email', 'community-yard-sale'),
                'street' => __('Street', 'community-yard-sale'),
                'unit' => __('Unit/Apartment', 'community-yard-sale'),
                'city' => __('City', 'community-yard-sale'),
                'state' => __('State', 'community-yard-sale'),
                'zip' => __('Zip', 'community-yard-sale'));
    }

    /**
     * @param  $atts array shortcode inputs associative array
     * @return string shortcode content
     */
    public function handleShortcode($atts) {
        $event = 'Untitled';
        if (isset($atts['event'])) {
            $event = $atts['event'];
        }
        if (isset($atts['mapheight'])) {
            $this->mapHeight = $atts['mapheight'];
        }
        if (isset($atts['mapwidth'])) {
            $this->mapWidth = $atts['mapwidth'];
        }

        if (isset($atts['lat'])) {
            $this->lat = $atts['lat'];
        }
        if (isset($atts['lng'])) {
            $this->lng = $atts['lng'];
        }
        if (isset($atts['zoom'])) {
            $this->zoom = $atts['zoom'];
        }

        // Form Options
        if (isset($atts['city'])) {
            $this->formOptions['city'] = explode(",", $atts['city']);
        }
        if (isset($atts['state'])) {
            $this->formOptions['state'] = explode(",", $atts['state']);
        }
        if (isset($atts['zip'])) {
            $this->formOptions['zip'] = explode(",", $atts['zip']);
        }

        // Form Defaults
        if (isset($atts['citydefault'])) {
            $this->formDefaults['city'] = $atts['citydefault'];
        }
        if (isset($atts['statedefault'])) {
            $this->formDefaults['state'] = $atts['statedefault'];
        }
        if (isset($atts['zipdefault'])) {
            $this->formDefaults['zip'] = $atts['zipdefault'];
        }

        // Form Labels
        if (isset($atts['emaillabel'])) {
            $this->formLabels['email'] = $atts['emaillabel'];
        }
        if (isset($atts['streetlabel'])) {
            $this->formLabels['street'] = $atts['streetlabel'];
        }
        if (isset($atts['unitlabel'])) {
            $this->formLabels['unit'] = $atts['unitlabel'];
        }
        if (isset($atts['citylabel'])) {
            $this->formLabels['city'] = $atts['citylabel'];
        }
        if (isset($atts['statelabel'])) {
            $this->formLabels['state'] = $atts['statelabel'];
        }
        if (isset($atts['ziplabel'])) {
            $this->formLabels['zip'] = $atts['ziplabel'];
        }

        // Form omissions
        if (isset($atts['omit'])) {
            $this->omits = explode(',', $atts['omit']);
        }

        ob_start();

        //        echo "\n<script type='text/javascript' src='http://maps.google.com/maps/api/js?sensor=false'></script>\n";
        //        echo "\n<script type='text/javascript' src='" . plugins_url('js/YSFormJS.js', __FILE__) . "'></script>\n";

        $suffix = $this->generateUniqueId();
        $this->formJS = 'ysFormJs_' . $suffix;
        $this->formId = 'ysForm_' . $suffix;
        $this->mapId = 'ysMap_' . $suffix;

        echo "\n<script type=\"text/javascript\">\n";
        printf('    var %s = new YSFormJS("%s", "%s", %s, %s, %s);',
               $this->formJS,
               $this->formId,
               $this->mapId,
               $this->lat,
               $this->lng,
               $this->zoom);
        echo "\n</script>";

        // Inject CSS. By the time this code is executed, the header is already sent, so
        // we have to in-line the CSS
        //        echo '<link rel="stylesheet" href="' . plugins_url('css/form.css', __FILE__). '" type="text/css" media="all" />' . "\n";

        $this->handleFormSubmission($event);
        $this->outputForm();

        $retVal = ob_get_contents();
        ob_end_clean();
        return $retVal;
    }


    /**
     * @param $event string
     * @return void
     */
    public function handleFormSubmission($event) {
        if (!$this->plugin) {
            $this->plugin = new YSPlugin();
        }

        global $wpdb;
        $this->messageToUser = '';
        $this->showForm = true;
        $tableName = $this->plugin->getTableName();

        // PROCESS FORM SUBMISSION
        //print_r($_POST); // DEBUG

        if (isset($_POST['_wpnonce']) &&
            isset($_POST['email']) &&
            isset($_POST['street']) && // no unit is OK
            isset($_POST['city']) &&
            isset($_POST['state']) &&
            isset($_POST['zip']) &&
            isset($_POST['listing']) &&
            isset($_POST['latlng'])
        ) {

            $nonce = $_POST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'yardsale')) die('Security check');

            $matches = array();
            if (preg_match("/\((.+), (.+)\)/", $_POST['latlng'], $matches)) { // e.g. (39.006579, -77.516362)
                $lat = $matches[1];
                $lng = $matches[2];
            }
            else {
                die('No lat/lng');
            }

            $email = $_POST['email'];
            $street = $_POST['street'];
            $unit = $_POST['unit'];
            $city = $_POST['city'];
            $state = $_POST['state'];
            $zip = $_POST['zip'];
            $listing = $_POST['listing'];

            $ip = isset($_SERVER['X_FORWARDED_FOR']) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

            if (! empty($_POST['ysid'])) {
                // Update database entry
                $ysid = esc_sql($_POST['ysid']);
                $wpdb->show_errors(); // debug
                $rows = $wpdb->query("UPDATE `$tableName` set
            `email` = '$email', `event`='$event', `lat` = '$lat', `lng` = '$lng',
            `street` = '$street', `unit` = '$unit', `city` = '$city', `state` = '$state', `zip` = '$zip',
            `ip` = '$ip',
            `listing` = '$listing'
        WHERE `id` = '$ysid'");

                $this->messageToUser = $rows ? __('Your entry has been updated', 'community-yard-sale')
                        : __('There was a problem updating your entry. Try again or contact an administrator', 'community-yard-sale');
                $this->showForm = false;
            }
            else {
                $id = $this->generateUniqueId(); // use this as the the key for the entry
                $wpdb->show_errors(); // debug

                // check for duplicate address entry
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$tableName` WHERE `event` = '$event' AND upper(street) = upper('$street') AND upper(unit) = upper('$unit') and upper(city) = upper('$city') AND upper(state) = upper('$state') AND upper(zip) = upper('$zip') ");
                if ($count) {
                    $this->messageToUser = __('There is already an entry for that address. ' .
                                              'If you are updating an entry, use the link sent to you in an email when you first entered your listing. ' .
                                              'If you share the address, use a different unit number', 'community-yard-sale');

                }
                else {

                    // insert into database
                    $rows = $wpdb->query("INSERT INTO `$tableName`
                (`id`, `email`, `event`, `lat`, `lng`, `street`, `unit`, `city`, `state`, `zip`, `ip`, `listing`) VALUES
                ('$id', '$email', '$event', '$lat', '$lng', '$street', '$unit', '$city', '$state', '$zip', '$ip', '$listing')");


                    if ($rows) {
                        $editUrl = get_permalink();
                        $editUrl .= (strpos($editUrl, '?') === false) ? "?ysid=$id" : "&ysid=$id";
                        $deleteUrl = $this->plugin->getDeleteIdUrl() . $id;

                        $editMessage = __('Edit Yard Sale Entry', 'community-yard-sale');
                        $deleteMessage = __('Delete Yard Sale Entry', 'community-yard-sale');
                        $linksHtml =
                                "<a href=\"$editUrl\">$editMessage</a><br/><br/>" .
                                "<a href=\"$deleteUrl\">$deleteMessage</a><br/><br/>";

                        $this->messageToUser = '<p>' .
                                __('Your listing has been saved. An email will be send to you with a links to edit and delete your listing', 'community-yard-sale') .
                                '</p>' . $linksHtml;

                        $emailMessage = '<p>' .
                                __('Thank you for your entry. Use the following links to edit or delete your entry.', 'community-yard-sale') .
                                '</p>' . $linksHtml .
                                "<p>$listing</p>";
                        $headers = array('From: ' . __('Yard Sale No-Reply', 'community-yard-sale') .
                                         ' <no-reply@' . $this->plugin->getEmailDomain() . '>' ,
                                         'Content-Type: text/html');
                        $h = implode("\r\n", $headers) . "\r\n";
                        wp_mail($_POST['email'], __('Yard Sale Entry', 'community-yard-sale'), $emailMessage, $h);
                    }
                    else {
                        $this->messageToUser = __('There was a problem saving your entry. Try again or contact an administrator', 'community-yard-sale');
                    }
                }

                $this->showForm = false;
            }

        } // END PROCESS FORM SUBMISSION


        // IF UPDATE LINK WAS CLICKED, PULL UP DATA TO PRE-POPULATE FORM
        if (isset($_GET['ysid'])) {
            $id = $_GET['ysid'];
            $wpdb->show_errors(); // debug
            $rows = $wpdb->get_results("select * from `$tableName` where `id` = '$id'");
            if ($rows && count($rows) == 1) {
                $this->data['ysid'] = $_GET['ysid'];
                //$this->data['latlng'] = // don't need this, it gets regenerated
                $this->data['email'] = $rows[0]->email;
                $this->data['street'] = $rows[0]->street;
                $this->data['unit'] = $rows[0]->unit;
                $this->data['city'] = $rows[0]->city;
                $this->data['state'] = $rows[0]->state;
                $this->data['zip'] = $rows[0]->zip;
                $this->data['listing'] = $rows[0]->listing;
            }
        }
        else {
            // SET DEFAULTS ON THE FORM
            $this->data['city'] = $this->formDefaults['city'];
            $this->data['state'] = $this->formDefaults['state'];
            $this->data['zip'] = $this->formDefaults['zip'];
        }

        //        $ysDateArray = get_post_custom_values('yardsale-date', 2447);
        //        $ysDateText = "";
        //        if ($ysDateArray[0]) {
        //            $ysDateText = $ysDateArray[0];
        //        }

        echo $this->messageToUser;
    }


    /**
     * @return void output echoed
     */
    public function outputForm() {

        if (!$this->plugin) {
            $this->plugin = new YSPlugin();
        }


        // IF NO SUBMISSION ERRORS, THEN SHOW THE FORM
        if ($this->showForm) {
            ?>
        <div class="entry_div">
            <p>
                <?php _e('If you wish to edit a listing that you have already made, use the link sent to you in email when you created it.', 'community-yard-sale'); ?>
            </p>

            <form id="<?php echo $this->formId ?>" action="" method="post">
                <?php wp_nonce_field('yardsale'); ?>
                <input name="ysid" type="hidden" value="<?php echo isset($this->data['ysid']) ? $this->data['ysid'] : '' ?>"/>
                <input name="latlng" type="hidden" value="<?php echo isset($this->data['latlng']) ? $this->data['latlng'] : ''?>"/>
                <table cellpadding="0px" cellspacing="0px">
                    <tbody>
                    <tr>
                        <td><label for="email"><?php echo htmlentities($this->formLabels['email']) ?>*</label></td>
                        <td><input name="email" id="email" type="text" size="30" value="<?php echo isset($this->data['email']) ? $this->data['email'] : ''?>" onblur="<?php echo $this->formJS ?>.fetchLatLong()"/></td>
                    </tr>
                    <tr>
                        <td><label for="street"><?php echo htmlentities($this->formLabels['street']) ?>*</label></td>
                        <td><input name="street" id="street" type="text" size="30" value="<?php echo isset($this->data['street']) ? $this->data['street'] : ''?>" onblur="<?php echo $this->formJS ?>.fetchLatLong()"/></td>
                    </tr>
                    <?php if (! in_array('unit', $this->omits)) { ?>
                    <tr>
                        <td><label for="unit"><?php echo htmlentities($this->formLabels['unit']) ?></label></td>
                        <td><input name="unit" id="unit" type="text" size="5" value="<?php echo isset($this->data['unit']) ? $this->data['unit'] : ''?>" onblur="<?php echo $this->formJS ?>.fetchLatLong()"/></td>
                    </tr>
                    <?php }
                    if (! in_array('city', $this->omits)) { ?>
                    <tr>
                        <?php $this->outputFieldWithOptionsAndDefaults('city', $this->formLabels['city'], '30') ?>
                    </tr>
                    <?php }
                    if (! in_array('state', $this->omits)) { ?>
                    <tr>
                        <?php $this->outputFieldWithOptionsAndDefaults('state', $this->formLabels['state'], '2') ?>
                    </tr>
                    <?php }
                    if (! in_array('zip', $this->omits)) { ?>
                    <tr>
                        <?php $this->outputFieldWithOptionsAndDefaults('zip', $this->formLabels['zip'], '10') ?>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <label for="listing"><?php _e('Details', 'community-yard-sale') ?>*</label><br/>
                <textarea name="listing" id="listing" rows="10" cols="30"><?php echo isset($this->data['listing']) ? $this->data['listing'] : ''?></textarea>
                <br/>
                <input onclick="<?php echo $this->formJS ?>.fetchLatLong(); return <?php echo $this->formJS ?>.validate();" type="submit" value="Submit"/>
                <?php
                foreach ($this->omits as $omit) {
                    $value = '';
                    $defaultValues = isset($this->formDefaults[$omit]) ? $this->formDefaults[$omit] : '';
                    if ($this->data[$omit] != $defaultValues) {
                        $value = $this->data[$omit];
                    }
                    else if (strpos($defaultValues, ',') === FALSE) {
                        // Is a single default option so set it
                        $value = $defaultValues;
                    }
                    ?>
                    <input type="hidden" id="<?php echo $omit ?>" name="<?php echo $omit ?>" value="<?php echo $value ?>"/>
                <?php
                }
                ?>
            </form>
        </div>
        <div class="map_div">
            <div class="map_canvas" id="<?php echo $this->mapId ?>"
                 style="height: <?php echo $this->mapHeight ?>; width: <?php echo $this->mapWidth ?>"></div>
        </div>
        <script type="text/javascript">
                <?php echo $this->formJS ?>.initGoogleMap();
        </script>

        <?php

        } // $this->showForm
    }

    public function outputFieldWithOptionsAndDefaults($field, $label, $textFieldSize) {
        ?>
    <td><label for="<?php echo $field ?>"><?php echo $label ?></label></td>
    <td>
        <?php
        if (empty($this->formOptions[$field])) {
            // Output a plain text field
            ?>
            <input name="<?php echo $field ?>" id="<?php echo $field ?>" type="text" size="<?php echo $textFieldSize ?>" value="<?php echo isset($this->data[$field]) ? $this->data[$field] : ''?>" onblur="<?php echo $this->formJS ?>.fetchLatLong()"/>
            <?php
        }
        else {
            // Output a select tag
            ?>
            <select name="<?php echo $field ?>" id="<?php echo $field ?>" onchange="<?php echo $this->formJS ?>.fetchLatLong()">
            <?php
            foreach ($this->formOptions[$field] as $val) {
                ?>
                <option value="<?php echo $val ?>" <?php echo isset($this->data[$field]) && $this->data[$field] == $val ? 'selected' : '' ?>><?php echo $val ?></option>
                <?php
            }
            ?>
            </select>
            <?php
        }
        ?>
    </td>
        <?php
    }

    public function generateUniqueId() {
        global $wpdb;
        if (!$this->plugin) {
            $this->plugin = new YSPlugin();
        }
        $tableName = $this->plugin->getTableName();
        do {
            $id = uniqid();
            // Ensure no duplicates
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(`id`) FROM `$tableName` WHERE `id` = %s", $id));
        } while ($count);
        return $id;
    }

}
