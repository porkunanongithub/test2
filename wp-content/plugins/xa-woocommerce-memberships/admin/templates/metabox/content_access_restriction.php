<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<select id="hforce_content_access_restriction" class="wc-enhanced-select" name="_hforce_content_access_restriction[]" multiple="multiple">
    <?php
    
            $none_selected = $all_members_selected= FALSE;
            if (in_array('none', $content_access_restriction)) {
                $none_selected = TRUE;
            }
            if (in_array('all_members', $content_access_restriction)) {
                $all_members_selected = TRUE;
            }
    
    if (!empty($memebership_plans)) {

        foreach ($memebership_plans as $plan) {

            if (in_array($plan->id, $content_access_restriction)) {
                echo '<option value="' . $plan->id . '" selected>' . $plan->name . '</option>';
            } else {
                echo '<option value="' . $plan->id . '">' . $plan->name . '</option>';
            }
        }
    }
    ?>
    <option value="none" <?php selected($none_selected, TRUE, true) ?> ><?php _e('Everyone', 'xa-woocommerce-membership') ?></option>
    <option value="all_members" <?php selected($all_members_selected, TRUE, true) ?> ><?php _e('All Members', 'xa-woocommerce-membership') ?></option>
    ?>
</select>