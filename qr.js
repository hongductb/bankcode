import express from 'express';
import cors from 'cors'; // Thêm thư viện CORS

const app = express();

// Cấu hình middleware
app.use(cors()); // Cho phép CORS cho tất cả các yêu cầu
app.use(express.json()); // Middleware để parse JSON

// Hàm tạo mã QR (chuyển từ PHP sang Node.js)
function genQRText(toAcc, accName, amount, msg, acqId) {
  const qrRawData = {
    qrType: "DYNAMIC",
    bin: acqId.padStart(6, "0"), // Đảm bảo mã người nhận có 6 chữ số
    receiverNumber: toAcc,
    amount: Math.floor(amount).toString(), // Đảm bảo định dạng không có phần thập phân
    orderId: "",
    description: msg,
  };

  const qrProperties = buildQRProps(qrRawData);
  const rawQRString = generateRawQRString(qrProperties);
  const qrContentNoChecksum = rawQRString + "6304"; // Cố định chiều dài mã QR
  const checksum = genCRC(qrContentNoChecksum);
  const qrContent = qrContentNoChecksum + checksum.toUpperCase();

  return qrContent;
}

// Hàm tạo QR Properties
function buildQRProps(qrRawData) {
  const isBankCard =
    qrRawData.receiverNumber.startsWith("9704") &&
    (qrRawData.receiverNumber.length === 16 ||
      qrRawData.receiverNumber.length === 19);

  return {
    payloadFormatIndicator: { id: "00", value: "01" },
    pointOfInitiationMethod: { id: "01", value: "12" },
    merchantAccountInformation: {
      id: "38",
      value: {
        guid: { id: "00", value: "A000000727" },
        paymentNetwork: {
          id: "01",
          value: {
            beneficiaryId: { id: "00", value: qrRawData.bin },
            receiverNumber: { id: "01", value: qrRawData.receiverNumber },
          },
        },
        servicesCode: { id: "02", value: isBankCard ? "QRIBFTTC" : "QRIBFTTA" },
      },
    },
    transactionCurrency: { id: "53", value: "704" },
    transactionAmount: { id: "54", value: qrRawData.amount },
    countryCode: { id: "58", value: "VN" },
    additionalDataFieldTemplate: {
      id: "62",
      value: {
        order: { id: "01", value: qrRawData.orderId },
        purposeOfTx: { id: "08", value: qrRawData.description },
      },
    },
  };
}

// Hàm tạo chuỗi QR
function generateRawQRString(objProps) {
  let result = "";
  for (const key in objProps) {
    const prop = objProps[key];
    if (!prop.value) continue;
    const isObject =
      typeof prop.value === "object" && !Array.isArray(prop.value);
    const valueString = isObject
      ? generateRawQRString(prop.value)
      : prop.value.toString();
    if (valueString) {
      result += prop.id;
      result += valueString.length.toString().padStart(2, "0");
      result += valueString;
    }
  }
  return result;
}

// Hàm tạo mã CRC
function genCRC(data) {
  let crc = 0xffff;
  for (let i = 0; i < data.length; i++) {
    let x = ((crc >> 8) ^ data.charCodeAt(i)) & 0xff;
    x ^= x >> 4;
    crc = ((crc << 8) ^ (x << 12) ^ (x << 5) ^ x) & 0xffff;
  }
  return crc.toString(16).toUpperCase().padStart(4, "0");
}

// Endpoint xử lý POST request
app.post("/generateQR", (req, res) => {
  const { accountNo, accountName, amount, acqId, addInfo = "" } = req.body;

  // Kiểm tra tham số truyền vào
  if (!accountNo || !accountName || !amount || !acqId) {
    return res.json({
      code: "01",
      desc: "Missing parameters!",
      data: null,
    });
  }

  // Tạo mã QR
  const qrCode = genQRText(accountNo, accountName, amount, addInfo, acqId);

  // Trả về JSON chứa mã QR
  res.json({
    code: "00",
    desc: "Gen QR successful!",
    data: {
      qrCode: qrCode,
    },
  });
});

// Xử lý các request không phải là POST
app.use((req, res) => {
  res.status(405).json({
    code: "02",
    desc: "Invalid request method!",
    data: null,
  });
});

// Cấu hình PORT cho CodeSandbox hoặc local
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});
