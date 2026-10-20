<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
 <h2><?= $editAddress?'Delivery address':'Delivering to your saved address' ?></h2>
 <?php if(!$editAddress): ?><a class="btn btn-outline-primary" href="<?= e(url('checkout',['edit_address'=>1])) ?>">Edit address</a><?php endif ?>
</div>
<?php if(!$editAddress): ?>
 <input type="hidden" name="address_mode" value="saved">
 <input type="hidden" name="address_id" value="<?= (int)$savedAddress['id'] ?>">
 <address class="mb-3" style="font-style:normal">
  <strong><?= e($savedAddress['name']) ?></strong><br>
  <?= e($savedAddress['address']) ?><br>
  <?= e($savedAddress['city'].', '.$savedAddress['state'].' '.$savedAddress['pincode']) ?><br>
  <?= e($savedAddress['mobile']) ?><br><?= e($savedAddress['email']) ?>
 </address>
 <?php if($savedAddress['instructions']): ?><p class="small">Delivery instructions: <?= e($savedAddress['instructions']) ?></p><?php endif ?>
 <p class="small muted">Your address is saved. No need to enter it again.</p>
<?php else: $addressValues=$savedAddress??user(); ?>
 <input type="hidden" name="address_mode" value="manual">
 <p class="small muted"><?= $savedAddress?'Update your address below. Your previous orders will keep their original delivery details.':'Enter your address once. We will remember it for your next orders.' ?></p>
 <div class="form-grid">
 <?php foreach(['name'=>'Full name','email'=>'Email address','mobile'=>'Mobile number','address'=>'Street address','city'=>'City','state'=>'State','pincode'=>'PIN code','instructions'=>'Delivery instructions (optional)'] as $key=>$label): ?>
  <label class="<?= in_array($key,['address','instructions'])?'full-width':'' ?>"><?= e($label) ?>
   <input class="form-control" name="<?= $key ?>" <?= $key!=='instructions'?'required':'' ?> type="<?= $key==='email'?'email':($key==='mobile'?'tel':'text') ?>" value="<?= e($addressValues[$key]??'') ?>" <?= $key==='mobile'?'pattern="[6-9][0-9]{9}" maxlength="10"':($key==='pincode'?'pattern="[1-9][0-9]{5}" maxlength="6"':'') ?>>
  </label>
 <?php endforeach ?>
 </div>
 <div class="d-flex align-items-center gap-3">
  <button type="submit" class="btn btn-outline-primary" formaction="<?= e(url('address/save')) ?>" formnovalidate>Save address</button>
  <?php if($savedAddress): ?><a class="text-link" href="<?= e(url('checkout')) ?>">Cancel</a><?php endif ?>
 </div>
<?php endif ?>
