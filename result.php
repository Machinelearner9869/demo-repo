<?php
session_start();
require 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["image"])) {
    try {
        $color_id = (int)$_POST["color_id"];
        $user_id = (int)$_SESSION["user_id"];

        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES["image"]["type"], $allowed_types)) {
            throw new Exception("Only JPG, PNG, and GIF images are allowed");
        }

        // Create uploads directory if it doesn't exist
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // Generate unique filename
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $filename = "uploads/" . uniqid() . "." . $file_ext;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $filename)) {
            throw new Exception("Failed to upload file");
        }

        // Call Flask API
        $curl = curl_init("http://localhost:5000/process");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            "image" => new CURLFile($filename),
            "color_id" => $color_id,
            "user_id" => $user_id
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Exception("API Error: " . curl_error($curl));
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            throw new Exception("API returned status code $http_code");
        }

        curl_close($curl);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['mask_path'])) {
            throw new Exception("Invalid API response");
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Results - Color Detection</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f8f9fa; padding-top: 20px; }
                .result-container { max-width: 1000px; margin: 0 auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .result-title { text-align: center; margin-bottom: 30px; color: #343a40; }
                .image-container { display: flex; justify-content: space-around; flex-wrap: wrap; margin-bottom: 30px; }
                .image-box { text-align: center; margin: 10px; flex: 1; min-width: 300px; }
                .result-image { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .percentage-display { font-size: 2.5rem; text-align: center; margin: 30px 0; padding: 20px; background-color: #f8f9fa; border-radius: 10px; color: #0d6efd; font-weight: bold; }
                .color-info { text-align: center; font-size: 1.5rem; margin-bottom: 20px; }
                .progress { height: 30px; margin: 20px 0; }
                .progress-bar { background-color: <?php echo $data['color_hex']; ?>; }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
                <div class="container">
                    <a class="navbar-brand" href="upload.php">Color Detection</a>
                    <div class="navbar-nav ms-auto">
                        <a class="nav-link" href="upload.php">Upload Another</a>
                        <a class="nav-link" href="logout.php">Logout</a>
                    </div>
                </div>
            </nav>

            <div class="container">
                <div class="result-container">
                    <h2 class="result-title">Color Detection Results</h2>

                    <div class="color-info">
                        Detected Color:
                        <strong style="color: <?php echo htmlspecialchars($data['color_hex']); ?>">
                            <?php echo htmlspecialchars($data['color_name']); ?>
                        </strong>
                    </div>

                    <div class="percentage-display">
                        <?php echo htmlspecialchars($data['percentage']); ?>% Match
                    </div>

                    <div class="progress">
                        <div class="progress-bar" role="progressbar"
                             style="width: <?php echo htmlspecialchars($data['percentage']); ?>%"
                             aria-valuenow="<?php echo htmlspecialchars($data['percentage']); ?>"
                             aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>

                    <div class="image-container">
                        <div class="image-box">
                            <h4>Original Image</h4>
                            <img src="<?php echo htmlspecialchars($filename); ?>" class="result-image">
                        </div>

                        <div class="image-box">
                            <h4>Color Detection</h4>
                            <img src="<?php echo htmlspecialchars($data['mask_path']); ?>" class="result-image">
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="upload.php" class="btn btn-primary btn-lg">Analyze Another Image</a>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        header("Location: upload.php?error=" . urlencode($error));
        exit();
    }
}

header("Location: upload.php");
exit();
?>