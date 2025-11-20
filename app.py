from flask import Flask, request, jsonify
import cv2
import numpy as np
import os
import mysql.connector
from datetime import datetime
from werkzeug.utils import secure_filename

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'static/uploads'
app.config['MASK_FOLDER'] = 'static/masks'
app.config['ALLOWED_EXTENSIONS'] = {'png', 'jpg', 'jpeg', 'gif'}

# Create directories if they don't exist
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
os.makedirs(app.config['MASK_FOLDER'], exist_ok=True)


def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="BHUwan9869@",
        database="hsv_color_detection"
    )


def allowed_file(filename):
    return '.' in filename and \
        filename.rsplit('.', 1)[1].lower() in app.config['ALLOWED_EXTENSIONS']


@app.route('/process', methods=['POST'])
def process_image():
    if 'image' not in request.files:
        return jsonify({"error": "No image file provided"}), 400

    img_file = request.files['image']
    if img_file.filename == '':
        return jsonify({"error": "No selected file"}), 400

    if not allowed_file(img_file.filename):
        return jsonify({"error": "Invalid file type"}), 400

    try:
        color_id = int(request.form['color_id'])
        user_id = int(request.form['user_id'])

        # Get color data from database
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM colors WHERE color_id=%s", (color_id,))
        color = cursor.fetchone()

        if not color:
            return jsonify({"error": "Color not found"}), 404

        hsv_lower = np.array([int(x) for x in color['hsv_lower'].split(',')])
        hsv_upper = np.array([int(x) for x in color['hsv_upper'].split(',')])

        # Save uploaded image
        filename = secure_filename(f"{datetime.now().strftime('%Y%m%d%H%M%S')}_{img_file.filename}")
        img_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        img_file.save(img_path)

        # Process image
        img = cv2.imread(img_path)
        if img is None:
            return jsonify({"error": "Could not read image file"}), 400

        hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
        mask = cv2.inRange(hsv, hsv_lower, hsv_upper)

        # Create colored mask
        hex_color = color['color_hex'].lstrip('#')
        rgb_color = tuple(int(hex_color[i:i + 2], 16) for i in (0, 2, 4))
        bgr_color = (rgb_color[2], rgb_color[1], rgb_color[0])  # Convert RGB to BGR

        # Create solid color mask
        colored_mask = np.zeros_like(img)
        colored_mask[mask != 0] = bgr_color

        # Create transparent overlay (70% original, 30% color)
        result = cv2.addWeighted(img, 0.7, colored_mask, 0.3, 0)

        # Calculate percentage
        total_pixels = mask.size
        colored_pixels = cv2.countNonZero(mask)
        percentage = round((colored_pixels / total_pixels) * 100, 2)

        # Save result
        mask_filename = f"colored_mask_{filename}"
        mask_path = os.path.join(app.config['MASK_FOLDER'], mask_filename)
        cv2.imwrite(mask_path, result)

        # Store results in database
        cursor.execute("INSERT INTO images (user_id, file_name) VALUES (%s, %s)",
                       (user_id, filename))
        image_id = cursor.lastrowid

        cursor.execute("""
            INSERT INTO image_colors (image_id, color_id, percentage) 
            VALUES (%s, %s, %s)
        """, (image_id, color_id, percentage))

        cursor.execute("""
            INSERT INTO color_regions (image_id, color_id, mask_image_path) 
            VALUES (%s, %s, %s)
        """, (image_id, color_id, mask_path))

        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({
            "mask_path": f"/{mask_path}",
            "percentage": percentage,
            "color_name": color['color_name'],
            "color_hex": color['color_hex']
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)