<?php
/**
 * AiscanController — AI Coffee Scan page + upload endpoint.
 * Route: index.php?url=aiscan  and  index.php?url=aiscan/upload
 */
class AiscanController extends Controller
{
    public function index(): void
    {
        $userId = $this->requireAuth();
        $scan = $this->model('AiScan');
        $this->view('game/aiscan', [
            'title'   => 'AI Coffee Scan',
            'history' => $scan->history($userId),
            'active'  => 'aiscan',
        ]);
    }

    /** POST multipart: field "image" (+ optional "features" JSON). Returns JSON prediction. */
    public function upload(): void
    {
        $userId = $this->requireAuth(true);

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'no_file',
                'message' => "No image received — pick a photo or use your camera."], 400);
        }
        $file = $_FILES['image'];
        if ($file['size'] > 6 * 1024 * 1024) {
            $this->json(['ok' => false, 'error' => 'too_large',
                'message' => "That image is over 6MB — try a smaller photo."], 400);
        }

        // Validate it is genuinely an image (works without the GD extension).
        $info = @getimagesize($file['tmp_name']);
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($extByMime[$info['mime']])) {
            $this->json(['ok' => false, 'error' => 'unreadable',
                'message' => "Hmm, I couldn't read that as a photo. Please upload a JPG, PNG or WEBP of a coffee."], 400);
        }

        if (!is_dir(UPLOAD_PATH)) @mkdir(UPLOAD_PATH, 0777, true);
        $name = 'scan_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $extByMime[$info['mime']];
        $dest = UPLOAD_PATH . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['ok' => false, 'error' => 'save_failed',
                'message' => "Couldn't save the image. Please try again."], 500);
        }

        // Colour features measured in the browser (this PHP build has no GD).
        $features = null;
        if (!empty($_POST['features'])) {
            $decoded = json_decode($_POST['features'], true);
            if (is_array($decoded)) $features = $decoded;
        }

        $publicPath = 'uploads/' . $name;
        $scan = $this->model('AiScan');
        $this->json($scan->analyze($userId, $dest, $publicPath, $features));
    }
}
