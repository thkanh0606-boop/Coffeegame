<?php $pageScript = 'aiscan'; ?>
<div class="page">
  <h1 class="mb-2">AI Coffee Scan</h1>
  <p class="muted mb-3">Upload a coffee photo — the scanner predicts the drink, recipe and prep steps.</p>

  <div class="grid-2">
    <!-- Uploader -->
    <div class="panel panel-pad">
      <span class="label-tab panel-title">Scan</span>
      <div class="mt-4">
        <div class="dropzone" id="dropzone">
          <div class="dz-ic"><?= icon('scan','svg') ?></div>
          <p class="b mt-2">Drop a coffee photo here</p>
          <p class="small muted">or click to browse — JPG / PNG / WEBP, max 6MB</p>
        </div>
        <input type="file" id="fileInput" accept="image/*" class="hidden">

        <button class="btn btn-block mt-3" id="btnCamera"><?= icon('scan','svg') ?> Use Camera</button>

        <!-- Live camera -->
        <div id="cameraWrap" class="hidden mt-3 text-center">
          <video id="camVideo" class="scan-preview" playsinline muted></video>
          <div class="flex gap-2 mt-2">
            <button class="btn btn-dark grow" id="btnCapture">Capture</button>
            <button class="btn grow" id="btnStopCam">Cancel</button>
          </div>
        </div>

        <!-- Preview -->
        <div id="previewWrap" class="mt-3 hidden text-center">
          <img id="preview" class="scan-preview" alt="preview">
          <button class="btn btn-dark btn-block mt-3" id="btnAnalyze"><?= icon('scan','svg') ?> Analyze</button>
        </div>
      </div>
    </div>

    <!-- Result -->
    <div class="panel panel-pad">
      <span class="label-tab panel-title">Prediction</span>
      <div id="scanResult" class="mt-4">
        <p class="muted text-center">Results will appear here.</p>
      </div>
    </div>
  </div>

  <!-- History -->
  <div class="panel panel-pad mt-4">
    <span class="label-tab panel-title">Scan History</span>
    <div class="mt-4" id="historyList">
      <?php if (empty($history)): ?>
        <p class="muted">No scans yet.</p>
      <?php else: foreach ($history as $h):
        $ings = json_decode($h['ingredients'] ?? '[]', true) ?: [];
      ?>
        <div class="row-item">
          <?php if (!empty($h['image_path'])): ?>
            <img src="<?= asset(e($h['image_path'])) ?>" class="ic" style="object-fit:cover;border-radius:10px;border:2px solid #111" alt="">
          <?php else: ?>
            <div class="ic"><?= icon('cup','svg') ?></div>
          <?php endif; ?>
          <div class="grow">
            <div class="b"><?= e($h['drink_name']) ?> <span class="lv"><?= e(implode(', ', $ings)) ?></span></div>
            <div class="small muted"><?= e($h['created_at']) ?></div>
          </div>
          <span class="chip"><?= (float)$h['confidence'] ?>%</span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
