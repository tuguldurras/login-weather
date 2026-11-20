<?php
header("Content-Type: application/json; charset=utf-8");
ini_set("display_errors", "0");

function out($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require "db.php";

    $CLIENT_ID = "641439662458-5ir4gilsco95l8qndhnd80q6t37iijr5.apps.googleusercontent.com";
    $in = json_decode(file_get_contents("php://input"), true) ?: [];

    // 1) Email + нууц үгээр нэвтрэх
    if (($in["action"] ?? "") === "login") {
        $st = $pdo->prepare("SELECT * FROM hereglegch WHERE email = ?");
        $st->execute([trim($in["email"] ?? "")]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($in["password"] ?? "", $u["nuuts_ug"])) {
            out(["ok" => true, "name" => $u["ner"]]);
        }
        out(["ok" => false, "msg" => "Email эсвэл нууц үг буруу байна."]);
    }

    // 2) Google-ээр бүртгүүлэх / нэвтрэх
    $token = $in["credential"] ?? "";
    if ($token === "") out(["ok" => false, "msg" => "Буруу хүсэлт."]);

    $res = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token));
    $g = $res ? json_decode($res, true) : null;

    if (!$g || ($g["aud"] ?? "") !== $CLIENT_ID || empty($g["email"])) {
        out(["ok" => false, "msg" => "Google баталгаажуулалт амжилтгүй боллоо."]);
    }

    $email = $g["email"];
    $ner   = $g["given_name"]  ?? "-";
    $ovog  = $g["family_name"] ?? "-";

    $st = $pdo->prepare("SELECT ner FROM hereglegch WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) out(["ok" => true, "name" => $u["ner"]]);

    $st = $pdo->prepare("INSERT INTO hereglegch (ovog, ner, email, nuuts_ug) VALUES (?,?,?,?)");
    $st->execute([$ovog, $ner, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    out(["ok" => true, "name" => $ner]);

} catch (Throwable $e) {
    out(["ok" => false, "msg" => "Серверийн алдаа: " . $e->getMessage()]);
}
