<div class="entra-action-wrapper">
    <h3>Actions</h3>
    <div class="row">
        <div class="col-md-12">
            <?php echo form_open('/ms-entra-browser/perform-action', array('id' => 'entra-action-form', 'autocomplete' => 'off', 'aria-autocomplete' => 'off', 'class' => 'vertical-80-pct')); ?>
                <div class="form-group">
                    <label class="control-label" for="entra-actions">Action</label>	
                    <?php echo form_dropdown('entra_action', $actions, $set_action, 'class="selectpicker form-control" data-live-search="true" data-size="8" id="entra-actions"'); ?>
                </div>

                <input type="hidden" name="client_code" value="<?php echo $client_code; ?>">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <div id="entra-action-submit-btn" class="text-center">
                    <button type="submit" disabled="" form="entra-action-form" class="btn btn-primary" data-loading-text="Submitting...">Submit</button>
                </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>