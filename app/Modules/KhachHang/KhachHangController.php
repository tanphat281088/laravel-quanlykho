<?php

namespace App\Modules\KhachHang;

use App\Http\Controllers\Controller;
use App\Modules\KhachHang\Validates\CreateKhachHangRequest;
use App\Modules\KhachHang\Validates\UpdateKhachHangRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\KhachHangImport;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\LoaiKhachHang;

class KhachHangController extends Controller
{
  protected $khachHangService;

  public function __construct(KhachHangService $khachHangService)
  {
    $this->khachHangService = $khachHangService;
  }

  /**
   * Lấy danh sách KhachHangs
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->khachHangService->getAll($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([
      'collection' => $result['data'],
      'total' => $result['total'],
      'pagination' => $result['pagination'] ?? null
    ]);
  }

  /**
   * Tạo mới KhachHang
   */
  public function store(CreateKhachHangRequest $request)
  {
    $result = $this->khachHangService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin KhachHang
   */
  public function show($id)
  {
    $result = $this->khachHangService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật KhachHang
   */
  public function update(UpdateKhachHangRequest $request, $id)
  {
    $result = $this->khachHangService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa KhachHang
   */
  public function destroy($id)
  {
    $result = $this->khachHangService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  public function getOptions()
  {
    $result = $this->khachHangService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/KhachHang.xlsx');

    if (!file_exists($path)) {
      return CustomResponse::error('File không tồn tại');
    }

    return response()->download($path);
  }

  public function importExcel(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv',
    ]);

    try {
      $data = $request->file('file');
      $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
      $path = $data->move(public_path('excel'), $filename);

      $import = new KhachHangImport($path);
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      if ($thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
      }

      return CustomResponse::success([
        'success' => $thanhCong,
        'fail' => $thatBai
      ], 'Import thành công ' . $thanhCong . ' bản ghi');
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Download template Excel có thêm sheet Loại Khách Hàng
   */
  public function downloadTemplateExcelWithLoaiKhachHang()
  {
    $fileName = "KhachHang";
    try {
      // Đọc file Excel hiện có
      $path = public_path('mau-excel/' . $fileName . '.xlsx');
      $spreadsheet = IOFactory::load($path);

      // Tạo sheet mới cho Loại Khách Hàng
      $newWorksheet = $spreadsheet->createSheet();
      $newWorksheet->setTitle('Loại Khách Hàng');

      // Lấy dữ liệu LoaiKhachHang
      $loaiKhachHangs = LoaiKhachHang::select('id', 'ten_loai_khach_hang')->where('trang_thai', 1)->get();

      // Thêm header
      $newWorksheet->setCellValue('A1', 'ID');
      $newWorksheet->setCellValue('B1', 'Tên Loại Khách Hàng');

      // Định dạng header
      $newWorksheet->getStyle('A1:B1')->getFont()->setBold(true);
      $newWorksheet->getStyle('A1:B1')->getAlignment()->setHorizontal('center');

      // Thêm dữ liệu
      $row = 2;
      foreach ($loaiKhachHangs as $loaiKhachHang) {
        $newWorksheet->setCellValue('A' . $row, $loaiKhachHang->id);
        $newWorksheet->setCellValue('B' . $row, $loaiKhachHang->ten_loai_khach_hang);
        $row++;
      }

      // Tự động điều chỉnh độ rộng cột
      $newWorksheet->getColumnDimension('A')->setAutoSize(true);
      $newWorksheet->getColumnDimension('B')->setAutoSize(true);

      // Tạo file tạm thời
      $tempPath = storage_path('app/temp_excel_' . time() . '.xlsx');
      $writer = new Xlsx($spreadsheet);
      $writer->save($tempPath);

      // Download file
      return response()->download($tempPath, $fileName . '.xlsx')->deleteFileAfterSend(true);
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi tạo file Excel: ' . $e->getMessage(), 500);
    }
  }
}