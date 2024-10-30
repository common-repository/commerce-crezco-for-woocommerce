<table class="form-table">
    <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="woocommerce_crezco_enabled">Connect with Crezco Connect</label>
            </th>
            <td class="forminp">
                <?php if (!empty($error)) { ?>
                    <div class="updated woocommerce-message">
                        <p>
                            <?php echo $error['warning']; ?>
                    </div>
                <?php } ?>
                <?php if (!empty($user_id)) { ?>
                    <div><?php echo $text_connect; ?></div>
                    <div class="button-primary" id="crezco-disconnect">Disconnect</div>
                <?php } else { ?>
                    <div class="button-primary" id="crezco-connect">Connect</div>
                <?php } ?>
            </td>
        </tr>
    </tbody>
</table>