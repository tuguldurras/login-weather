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
    $action = $in["action"] ?? "";

    $find = function ($email) use ($pdo) {
        $st = $pdo->prepare("SELECT * FROM hereglegch WHERE email = ?");
        $st->execute([$email]);
        return $st->fetch(PDO::FETCH_ASSOC);
    };

    // 1) Гараар бичиж бүртгүүлэх
    if ($action === "register") {
        $ovog  = trim($in["ovog"] ?? "");
        $ner   = trim($in["ner"] ?? "");
        $email = strtolower(trim($in["email"] ?? ""));
        $pass  = $in["password"] ?? "";

        if ($ovog === "" || $ner === "") out(["ok" => false, "msg" => "Овог, нэрээ бөглөнө үү."]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(["ok" => false, "msg" => "Email буруу байна."]);
        if (strlen($pass) < 6) out(["ok" => false, "msg" => "Нууц үг 6-аас дээш тэмдэгттэй байх ёстой."]);
        if ($find($email)) out(["ok" => false, "msg" => "Энэ email аль хэдийн бүртгэлтэй байна."]);

        $pdo->prepare("INSERT INTO hereglegch (ovog, ner, email, nuuts_ug) VALUES (?,?,?,?)")
            ->execute([$ovog, $ner, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        out(["ok" => true, "msg" => "Амжилттай бүртгэгдлээ! Одоо нэвтэрнэ үү."]);
    }

    // 2) Email + нууц үгээр нэвтрэх
    if ($action === "login") {
        $u = $find(strtolower(trim($in["email"] ?? "")));
        if ($u && password_verify($in["password"] ?? "", $u["nuuts_ug"])) {
            out(["ok" => true, "name" => $u["ner"]]);
        }
        out(["ok" => false, "msg" => "Email эсвэл нууц үг буруу байна."]);
    }

    // 3) Google-ээр бүртгүүлэх / нэвтрэх
    if ($action === "google") {
        $mode  = $in["mode"] ?? "login";
        $token = $in["credential"] ?? "";
        $res = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token));
        $g = $res ? json_decode($res, true) : null;

        if (!$g || ($g["aud"] ?? "") !== $CLIENT_ID || empty($g["email"])) {
            out(["ok" => false, "msg" => "Google баталгаажуулалт амжилтгүй боллоо."]);
        }
        $email = strtolower($g["email"]);
        $u = $find($email);

        if ($mode === "register") {
            if ($u) out(["ok" => false, "msg" => "Энэ email аль хэдийн бүртгэлтэй байна."]);
            $pdo->prepare("INSERT INTO hereglegch (ovog, ner, email, nuuts_ug) VALUES (?,?,?,?)")
                ->execute([$g["family_name"] ?? "-", $g["given_name"] ?? "-", $email,
                           password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
            out(["ok" => true, "name" => $g["given_name"] ?? "-"]);
        }

        if (!$u) out(["ok" => false, "msg" => "Энэ email бүртгэлгүй байна. Эхлээд Бүртгүүлэх tab-аар бүртгүүлнэ үү."]);
        out(["ok" => true, "name" => $u["ner"]]);
    }

    out(["ok" => false, "msg" => "Буруу хүсэлт."]);

} catch (Throwable $e) {
    out(["ok" => false, "msg" => "Серверийн алдаа: " . $e->getMessage()]);
}
