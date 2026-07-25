<?php
// ข้อมูลเมนูหลักของเว็บ — partials/header.php แค่วน loop render จากอาเรย์นี้
// วันหน้าย้ายไป MySQL (ตาราง menu_items) แค่แก้ไฟล์นี้ให้ query แทน ไม่ต้องแตะ header.php
return [
    ['label' => 'บทความ', 'href' => 'index.php', 'order' => 1],
    ['label' => 'ร่าง', 'href' => 'drafts.php', 'order' => 2],
];
