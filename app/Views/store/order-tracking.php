<section class="panel mb-4 order-tracking">
 <span class="eyebrow">YOUR ORDER JOURNEY</span><h2>Track your order</h2>
 <div class="d-flex gap-3 flex-wrap mb-3"><strong>Order: <?= e($order['status']) ?></strong><span>Payment: <?= e($order['payment_status']) ?><?= $order['payment_method']==='cod' && $order['payment_status']==='pending'?' (cash on delivery)':'' ?></span></div>
 <?php if($order['status']==='Cancelled'): ?><p class="alert alert-warning">This order has been cancelled. Payment status is shown separately above.</p><?php endif ?>
 <ol class="delivery-steps">
 <?php $steps=['Order placed','Confirmed','Processing','Packed','Shipped','Out for delivery','Delivered'];foreach($steps as $step):
 $event=null;foreach($orderHistory as $h)if($h['event_key']==='status:'.$step || ($step==='Order placed' && $h['event_key']==='placed') || ($step==='Confirmed' && $h['event_key']==='payment:paid'))$event=$h;
 $current=$order['status']===$step;$done=$event || $step==='Order placed'; ?>
 <li class="<?= $current?'current':($done?'done':'upcoming') ?>"><span class="step-dot" aria-hidden="true"><?= $done?'✓':'○' ?></span><div><strong><?= e($step) ?></strong><small><?= $event?e(date('d M Y, h:i A',strtotime($event['created_at']))):($step==='Order placed'?e(date('d M Y, h:i A',strtotime($order['created_at']))):($current?'Current status':'Not yet recorded')) ?></small></div></li>
 <?php endforeach ?>
 </ol>
 <?php if(!empty($order['expected_delivery'])): ?><p><strong>Estimated delivery:</strong> <?= e(date('d M Y',strtotime($order['expected_delivery']))) ?></p><?php endif ?>
 <?php if(!empty($order['courier'])): ?><p><strong>Courier:</strong> <?= e($order['courier']) ?></p><?php endif ?>
 <?php if($order['tracking']): ?><p><strong>Tracking reference:</strong> <?= e($order['tracking']) ?></p><?php endif ?>
 <?php if(!empty($order['tracking_url'])): ?><a class="btn btn-outline-primary" href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Track with courier ↗</a><?php endif ?>
 <p class="small muted mt-3">Updates appear when the store changes your order status. Courier tracking opens separately.</p>
 <?php if($orderHistory): ?><details><summary>All order updates</summary><?php foreach(array_reverse($orderHistory) as $h): ?><div class="py-2"><strong><?= e($h['title']) ?></strong><small class="d-block muted"><?= e(date('d M Y, h:i A',strtotime($h['created_at']))) ?></small><span><?= e($h['detail']) ?></span></div><?php endforeach ?></details><?php endif ?>
</section>
