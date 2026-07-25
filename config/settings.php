<?php
// ค่าตั้งค่าทั่วเว็บ (แบบเดียวกับหน้า General Settings ของ WP) — วันนี้เป็นไฟล์ธรรมดา
// วันหน้ามีหน้าแอดมินจัดการค่อยเปลี่ยน includes/settings.php ให้ query จากตาราง DB แทน
// โดยหน้าเว็บที่เรียกผ่าน siteSetting() ไม่ต้องแก้เลย
return [
    'site_name' => 'mBlog Web',
    'timezone' => 'Asia/Bangkok',
    'owner_email' => 'mblog@mblogofficial.com', // แก้เป็นอีเมลจริงของเจ้าของบล็อกได้เลย
    'footer_tagline' => 'สร้างด้วย PHP ล้วนๆ กับ ai อยากทำเองใช้เอง',
];
