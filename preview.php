<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once ('tcpdf/tcpdf/tcpdf.php');


$invoiceId = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;


session_start();


if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} else {

    exit('User session not found');
}


$servername = "************";
;
$username = "************";
$password = "************";
$dbname = "************";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$invoiceNumberQuery = "SELECT invoice_num FROM invoice_number WHERE user_id = ? ORDER BY id DESC LIMIT 1";
$stmtInvoiceNumber = $conn->prepare($invoiceNumberQuery);
$stmtInvoiceNumber->bind_param("i", $userId);
$stmtInvoiceNumber->execute();
$resultInvoiceNumber = $stmtInvoiceNumber->get_result();

if ($resultInvoiceNumber->num_rows > 0) {
    $row = $resultInvoiceNumber->fetch_assoc();
    $latestInvoiceNumber = $row['invoice_num'];
} else {

    exit('No invoice number found for the user');
}

$stmtInvoiceNumber->close();


$invoiceData = [];
$productData = [];

if ($invoiceId > 0) {

    $invoiceQuery = "SELECT * FROM invoices WHERE id = ?";
    $stmtInvoice = $conn->prepare($invoiceQuery);
    $stmtInvoice->bind_param("i", $invoiceId);
    $stmtInvoice->execute();
    $resultInvoice = $stmtInvoice->get_result();

    if ($resultInvoice->num_rows > 0) {
        $invoiceData = $resultInvoice->fetch_assoc();

        $invoiceData['invoiceNumber'] = $latestInvoiceNumber;
    }

    $stmtInvoice->close();


    $productQuery = "SELECT * FROM products WHERE invoice_id = ?";
    $stmtProduct = $conn->prepare($productQuery);
    $stmtProduct->bind_param("i", $invoiceId);
    $stmtProduct->execute();
    $resultProduct = $stmtProduct->get_result();

    if ($resultProduct->num_rows > 0) {
        while ($row = $resultProduct->fetch_assoc()) {
            $productData[] = $row;
        }
    }

    $stmtProduct->close();
}

$conn->close();


function downloadPDF($invoiceData, $productData)
{

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Your Company');
    $pdf->SetTitle('Invoice');
    $pdf->SetSubject('Invoice');


    $pdf->AddPage();


    $pdf->SetFont('helvetica', '', 8);


    $content = '
    <div class="invoice-container">
        <!-- Display company details -->
        <div class="company-details">
            <div style="font-size: 8px;">
                <h2>' . $invoiceData['companyName'] . '</h2>
                <p style="margin-bottom: 2px;">' . $invoiceData['companyAddress'] . '</p>
                <p style="margin-bottom: 2px;">' . $invoiceData['email'] . '</p>
                <p style="margin-bottom: 2px;">' . $invoiceData['phoneNumber'] . '</p>
            </div>

            <div class="additional-details" style="float: right;">
                <!-- Additional details at the top right corner -->
                <div style="text-align: right;">
                    <p>Invoice No: ' . $invoiceData['invoiceNumber'] . '</p>
                    <p>Terms: ' . $invoiceData['terms'] . '</p>
                    <p>Invoice Date: ' . $invoiceData['invoiceDate'] . '</p>
                    <p>Due Date: ' . $invoiceData['termsDue'] . '</p>
                </div>
            </div>
        </div>
        
        <!-- Display bill-to details above the table -->
        <div class="bill-to-details" style="font-size: 8px; margin-top: 10px;">
            <h3>Bill To:</h3>
            <p style="margin-bottom: 2px;">Name: ' . $invoiceData['billToName'] . '</p>
            <p>' . $invoiceData['billToAddress'] . '</p>
        </div>

        <!-- Display Product and Services table -->
        <div>
            <h3 style="font-size: 8px; margin-top: 10px;">Product and Services</h3>
            <table style="font-size: 8px; border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Item</th>
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Qty/Hrs</th>
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Rate</th>
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Discount %</th>
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Tax</th>
                        <th style="border: 1px solid #dddddd; text-align: left; padding: 4px;">Amount</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($productData as $product) {
        $content .= '
                    <tr>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['product'] . '</td>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['quantity'] . '</td>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['rate'] . '</td>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['discount'] . '</td>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['tax'] . '</td>
                        <td style="border: 1px solid #dddddd; text-align: left; padding: 4px;">' . $product['amount'] . '</td>
                    </tr>';
    }

    $content .= '
                </tbody>
            </table>
        </div>
        <br>
        
        <!-- Display additional details -->
        <div class="additional-details" style="float: right; text-align: right; padding-right: 10px; margin-top: 10px;">
            <div>
                <label for="balancePaid" style="font-weight: bold;">Balance Paid:</label>
                <span>' . number_format($invoiceData['balancePaid']) . '</span>
            </div>
            <br>
            <div>
                <label for="subTotal" style="font-weight: bold;">Sub Total:</label>
                <span>' . number_format(calculateSubTotal($productData)) . '</span>
            </div>
            <br>
            <div>
                <label for="balanceDue" style="font-weight: bold;">Balance Due: Naira</label>
                <span>' . number_format(calculateBalanceDue($invoiceData['balancePaid'], calculateSubTotal($productData))) . '</span>
            </div>
            <br>
        </div>
        
        <!-- Horizontal line -->
        <hr style="margin-top: 10px; border-top: 1px solid black;">
    </div>';


    $pdf->writeHTML($content, true, false, true, false, '');


    $pdf->Output('invoice.pdf', 'D');
}


function calculateSubTotal($productData)
{
    $subTotal = 0;
    foreach ($productData as $product) {
        $subTotal += $product['amount'];
    }
    return $subTotal;
}


function calculateBalanceDue($balancePaid, $subTotal)
{
    return $subTotal - $balancePaid;
}


if (isset($_POST['download'])) {
    downloadPDF($invoiceData, $productData);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
        }

        .invoice-container {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            overflow-x: auto;
        }

        .company-details h2,
        .company-details p,
        .bill-to-details h3,
        .bill-to-details p {
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background-color: #f2f2f2;
        }

        .additional-details {
            margin-top: 20px;
            text-align: right;
        }


        @media only screen and (max-width: 600px) {

            .invoice-container,
            table {
                width: 100%;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <h1>Invoice Preview</h1>
    <div class="invoice-container">

        <div class="company-details">
            <div>
                <h2>
                    <?php echo $invoiceData['companyName']; ?>
                </h2>
                <p>
                    <?php echo $invoiceData['companyAddress']; ?>
                </p>
                <p>
                    <?php echo $invoiceData['email']; ?>
                </p>
                <p>
                    <?php echo $invoiceData['phoneNumber']; ?>
                </p>
            </div>

            <div class="additional-details">

                <div>
                    <p>Invoice No:
                        <?php echo $invoiceData['invoiceNumber']; ?>
                    </p>
                    <p>Terms:
                        <?php echo $invoiceData['terms']; ?>
                    </p>
                    <p>Invoice Date:
                        <?php echo $invoiceData['invoiceDate']; ?>
                    </p>
                    <p>Due Date:
                        <?php echo $invoiceData['termsDue']; ?>
                    </p>
                </div>
            </div>
        </div>


        <div class="bill-to-details">
            <h3>Bill To:</h3>
            <p>Name:
                <?php echo $invoiceData['billToName']; ?>
            </p>
            <p>
                <?php echo $invoiceData['billToAddress']; ?>
            </p>
        </div>


        <div>
            <h3>Product and Services</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty/Hrs</th>
                        <th>Rate</th>
                        <th>Discount %</th>
                        <th>Tax</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productData as $product): ?>
                        <tr>
                            <td>
                                <?php echo $product['product']; ?>
                            </td>
                            <td>
                                <?php echo $product['quantity']; ?>
                            </td>
                            <td>
                                <?php echo $product['rate']; ?>
                            </td>
                            <td>
                                <?php echo $product['discount']; ?>
                            </td>
                            <td>
                                <?php echo $product['tax']; ?>
                            </td>
                            <td>
                                <?php echo $product['amount']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <br>

        <div class="additional-details">
            <div>
                <label for="balancePaid" style="font-weight: bold;">Balance Paid:</label>
                <span>
                    <?php echo number_format($invoiceData['balancePaid']); ?>
                </span>
            </div>
            <br>
            <div>
                <label for="subTotal" style="font-weight: bold;">Sub Total:</label>
                <span>
                    <?php echo number_format(calculateSubTotal($productData)); ?>
                </span>
            </div>
            <br>
            <div>
                <label for="balanceDue" style="font-weight: bold; text-decoration: underline;">
                    Balance Due: ₦
                </label>
                <span style="font-weight: bold; text-decoration: underline;">
                    <?php echo number_format(calculateBalanceDue($invoiceData['balancePaid'], calculateSubTotal($productData))); ?>
                </span>
            </div>
            <br>
        </div>
    </div>

    <form method="post" action="">
        <button type="submit" name="download">Download PDF</button>
    </form>
</body>

</html>