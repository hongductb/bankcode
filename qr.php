<?php
header("Content-Type: application/json; charset=UTF-8");

// Kiểm tra xem yêu cầu có phải là POST không
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Lấy dữ liệu JSON từ yêu cầu
    $input = json_decode(file_get_contents("php://input"), true);

    // Kiểm tra xem dữ liệu đã được truyền đúng cách
    if (isset($input['accountNo'], $input['accountName'], $input['amount'], $input['acqId'])) {
        $toAcc = $input['accountNo']; // Tài khoản nhận
        $accName = $input['accountName']; // Họ tên người nhận
        $acqId = $input['acqId']; // ID người nhận
        $amount = $input['amount']; // Số tiền
        $msg = isset($input['addInfo']) ? $input['addInfo'] : ''; // Nội dung bổ sung

        // Tạo mã QR
        $qrCode = genQRText($toAcc, $accName, $amount, $msg, $acqId);

        // Trả về JSON với thông tin mã QR
        echo json_encode([
            "code" => "00",
            "desc" => "Gen QR successful!",
            "data" => [
                "qrCode" => $qrCode
            ]
        ]);
    } else {
        // Nếu thiếu tham số, trả về thông báo lỗi
        echo json_encode([
            "code" => "01",
            "desc" => "Missing parameters!",
            "data" => null
        ]);
    }
} else {
    // Nếu không phải là yêu cầu POST, trả về thông báo lỗi
    echo json_encode([
        "code" => "02",
        "desc" => "Invalid request method!",
        "data" => null
    ]);
}

function genQRText($toAcc, $accName, $amount, $msg, $acqId) {
    $qrRawData = array(
        "qrType" => "DYNAMIC",
        "bin" => str_pad($acqId, 6, '0', STR_PAD_LEFT), // Đảm bảo mã người nhận có 6 chữ số
        "receiverNumber" => $toAcc,
        "amount" => number_format((float)$amount, 0, '', ''), // Đảm bảo định dạng không có phần thập phân
        "orderId" => '',
        "description" => $msg
    );

    $qrProperties = buildQRProps($qrRawData);
    $rawQRString = generateRawQRString($qrProperties);
    $qrContentNoChecksum = $rawQRString . "63" . "04"; // Cố định chiều dài mã QR
    $checksum = genCRC($qrContentNoChecksum);
    $qrContent = $qrContentNoChecksum . strtoupper($checksum);

    return $qrContent;
}

function buildQRProps($qrRawData) {
    $isBankCard = (substr($qrRawData["receiverNumber"], 0, 4) === "9704") && (strlen($qrRawData["receiverNumber"]) === 16 || strlen($qrRawData["receiverNumber"]) === 19);
    $qrProperties = array(
        "payloadFormatIndicator" => array("id" => "00", "value" => "01"),
        "pointOfInitiationMethod" => array("id" => "01", "value" => "12"),
        "merchantAccountInformation" => array("id" => "38", "value" => array(
            "guid" => array("id" => "00", "value" => "A000000727"),
            "paymentNetwork" => array(
                "id" => "01", "value" => array(
                    "beneficiaryId" => array("id" => "00", "value" => $qrRawData["bin"]),
                    "receiverNumber" => array("id" => "01", "value" => $qrRawData["receiverNumber"])
                )
            ),
            "servicesCode" => array("id" => "02", "value" => $isBankCard ? "QRIBFTTC" : "QRIBFTTA")
        )),
        "transactionCurrency" => array("id" => "53", "value" => "704"),
        "transactionAmount" => array("id" => "54", "value" => $qrRawData["amount"]),
        "countryCode" => array("id" => "58", "value" => "VN"),
        "additionalDataFieldTemplate" => array("id" => "62", "value" => array(
            "order" => array("id" => "01", "value" => $qrRawData["orderId"]),
            "purposeOfTx" => array("id" => "08", "value" => $qrRawData["description"])
        ))
    );

    return $qrProperties;
}

function generateRawQRString($objProps) {
    $result = '';
    foreach ($objProps as $prop) {
        if (!$prop['value']) continue;
        $isObject = is_array($prop['value']);
        $valueString = $isObject ? generateRawQRString($prop['value']) : (string) $prop['value'];
        if ($valueString) {
            $result .= $prop['id'];
            $result .= str_pad(strlen($valueString), 2, '0', STR_PAD_LEFT);
            $result .= $valueString;
        }
    }
    return $result;
}

function genCRC($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
        $x ^= ($x >> 4);
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    return strtoupper(dechex($crc));
}
?>
