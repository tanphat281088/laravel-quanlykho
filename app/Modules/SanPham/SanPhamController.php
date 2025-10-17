<?php

namespace App\Modules\SanPham;

use App\Http\Controllers\Controller;
use App\Models\DanhMucSanPham;
use App\Models\DonViTinh;
use App\Models\NhaCungCap;
use App\Modules\SanPham\Validates\CreateSanPhamRequest;
use App\Modules\SanPham\Validates\UpdateSanPhamRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SanPhamImport;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SanPhamController extends Controller
{
  protected $sanPhamService;

  public function __construct(SanPhamService $sanPhamService)
  {
    $this->sanPhamService = $sanPhamService;
  }

  /**
   * Lấy danh sách SanPhams
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->sanPhamService->getAll($params);

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
   * Tạo mới SanPham
   */
  public function store(CreateSanPhamRequest $request)
  {
    $result = $this->sanPhamService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin SanPham
   */
  public function show($id)
  {
    $result = $this->sanPhamService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật SanPham
   */
  public function update(UpdateSanPhamRequest $request, $id)
  {
    $result = $this->sanPhamService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa SanPham
   */
  public function destroy($id)
  {
    $result = $this->sanPhamService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách SanPham dạng option
   */
  public function getOptions(Request $request)
  {
    $params = $request->all();

    $params = Helper::validateFilterParams($params);

    $result = $this->sanPhamService->getOptions([
      ...$params,
      'limit' => -1,
    ]);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getOptionsByNhaCungCap($nhaCungCapId)
  {
    $result = $this->sanPhamService->getOptionsByNhaCungCap($nhaCungCapId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getOptionsLoSanPhamBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId)
  {
    $result = $this->sanPhamService->getOptionsLoSanPhamBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/SanPham.xlsx');

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

      $import = new SanPhamImport();
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
   * Download template Excel có thêm sheet Danh Mục Sản Phẩm, Đơn Vị Tính, Nhà Cung Cấp
   */
  public function downloadTemplateExcelWithRelations()
  {
    $fileName = "SanPham";
    try {
      // Đọc file Excel hiện có
      $path = public_path('mau-excel/' . $fileName . '.xlsx');
      $spreadsheet = IOFactory::load($path);

      // Tạo sheet mới cho Loại Khách Hàng
      $newWorksheet1 = $spreadsheet->createSheet();
      $newWorksheet1->setTitle('Danh Mục Sản Phẩm');
      $newWorksheet2 = $spreadsheet->createSheet();
      $newWorksheet2->setTitle('Đơn Vị Tính');
      $newWorksheet3 = $spreadsheet->createSheet();
      $newWorksheet3->setTitle('Nhà Cung Cấp');

      // Lấy dữ liệu
      $danhMucSanPhams = DanhMucSanPham::select('id', 'ten_danh_muc')->where('trang_thai', 1)->get();
      $donViTinhs      = DonViTinh::select('id', 'ten_don_vi')->where('trang_thai', 1)->get();
      $nhaCungCaps     = NhaCungCap::select('id', 'ten_nha_cung_cap')->where('trang_thai', 1)->get();

      // Header
      $newWorksheet1->setCellValue('A1', 'ID');
      $newWorksheet1->setCellValue('B1', 'Tên Danh Mục Sản Phẩm');
      $newWorksheet2->setCellValue('A1', 'ID');
      $newWorksheet2->setCellValue('B1', 'Tên Đơn Vị Tính');
      $newWorksheet3->setCellValue('A1', 'ID');
      $newWorksheet3->setCellValue('B1', 'Tên Nhà Cung Cấp');

      // Style
      $newWorksheet1->getStyle('A1:B1')->getFont()->setBold(true);
      $newWorksheet1->getStyle('A1:B1')->getAlignment()->setHorizontal('center');
      $newWorksheet2->getStyle('A1:B1')->getFont()->setBold(true);
      $newWorksheet2->getStyle('A1:B1')->getAlignment()->setHorizontal('center');
      $newWorksheet3->getStyle('A1:B1')->getFont()->setBold(true);
      $newWorksheet3->getStyle('A1:B1')->getAlignment()->setHorizontal('center');

      // Data
      $row = 2;
      foreach ($danhMucSanPhams as $danhMucSanPham) {
        $newWorksheet1->setCellValue('A' . $row, $danhMucSanPham->id);
        $newWorksheet1->setCellValue('B' . $row, $danhMucSanPham->ten_danh_muc);
        $row++;
      }
      $row = 2;
      foreach ($donViTinhs as $donViTinh) {
        $newWorksheet2->setCellValue('A' . $row, $donViTinh->id);
        $newWorksheet2->setCellValue('B' . $row, $donViTinh->ten_don_vi);
        $row++;
      }
      $row = 2;
      foreach ($nhaCungCaps as $nhaCungCap) {
        $newWorksheet3->setCellValue('A' . $row, $nhaCungCap->id);
        $newWorksheet3->setCellValue('B' . $row, $nhaCungCap->ten_nha_cung_cap);
        $row++;
      }

      // Auto width
      $newWorksheet1->getColumnDimension('A')->setAutoSize(true);
      $newWorksheet1->getColumnDimension('B')->setAutoSize(true);
      $newWorksheet2->getColumnDimension('A')->setAutoSize(true);
      $newWorksheet2->getColumnDimension('B')->setAutoSize(true);
      $newWorksheet3->getColumnDimension('A')->setAutoSize(true);
      $newWorksheet3->getColumnDimension('B')->setAutoSize(true);

      // Save temp
      $tempPath = storage_path('app/temp_excel_' . time() . '.xlsx');
      $writer   = new Xlsx($spreadsheet);
      $writer->save($tempPath);

      // Download
      return response()->download($tempPath, $fileName . '.xlsx')->deleteFileAfterSend(true);
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi tạo file Excel: ' . $e->getMessage(), 500);
    }
  }

  /* =========================================================
   |  PHẦN MỚI: Endpoint cho popup sản phẩm (2 giá + ảnh URL)
   |=========================================================*/

  /**
   * GET /api/san-pham/search-popup
   * Query: keyword, danh_muc_id, page, per_page
   * Trả về: { data: [...], meta: {...} }
   */
  public function searchPopup(Request $request)
  {
    $filters = [
      'keyword'     => $request->get('keyword'),
      'danh_muc_id' => $request->get('danh_muc_id'),
      'page'        => (int) $request->get('page', 1),
      'per_page'    => (int) $request->get('per_page', 20),
    ];

    $data = $this->sanPhamService->searchWithPricesAndPrimaryImage($filters);

    // Giữ format API đồng nhất
    return CustomResponse::success($data);
  }
}
