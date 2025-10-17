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

// BỔ SUNG
use App\Services\CustomerTotalsService;
use App\Support\Phone;
use App\Models\KhachHang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KhachHangController extends Controller
{
  protected $khachHangService;
  protected $totalsService; // BỔ SUNG

  public function __construct(KhachHangService $khachHangService, CustomerTotalsService $totalsService)
  {
    $this->khachHangService = $khachHangService;
    $this->totalsService    = $totalsService; // BỔ SUNG
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

  /**
   * Options cho dropdown: statuses(pipeline) / channels / staff / types
   * Trả đúng format FE đang parse; đồng thời giữ 'pipelines' để tương thích ngược.
   */
  public function getOptions()
  {
    try {
      $pipelines = DB::table('pipelines')
        ->where('active', 1)
        ->orderBy('sort_order')
        ->pluck('name')
        ->values();

      $channels = DB::table('channels')
        ->where('active', 1)
        ->orderBy('id')
        ->pluck('name')
        ->values();

      $staff = DB::table('users')
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

      $types = DB::table('loai_khach_hangs')
        ->select('id', 'ten_loai_khach_hang')
        ->orderBy('nguong_doanh_thu')
        ->get();

      // tên mới cho Pipeline là "statuses" (Tình trạng khách)
      $statuses = $pipelines;

      // Log chẩn đoán
      Log::info('[KH OPTIONS]', [
        'db'        => env('DB_DATABASE'),
        'pipelines' => $pipelines->count(),
        'channels'  => $channels->count(),
        'staff'     => $staff->count(),
        'types'     => $types->count(),
      ]);

      // DỮ LIỆU TRẢ VỀ — khớp FE
      $payload = compact('statuses','pipelines', 'channels', 'staff', 'types');
      return CustomResponse::success($payload);

    } catch (\Throwable $e) {
      Log::error('[KH OPTIONS][ERR] '.$e->getMessage());
      return CustomResponse::error('Lỗi lấy options: '.$e->getMessage(), 500);
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

  // =========================
  // BỔ SUNG 2 API CHUYÊN CRM
  // =========================

  /**
   * Tra cứu khách theo SĐT (chuẩn hoá + tìm nhanh)
   * GET /api/khach-hang/lookup/phone?phone=...
   */
  public function lookupByPhone(Request $request)
  {
    $phone = Phone::normalize($request->get('phone'));
    if (!$phone) {
      return CustomResponse::error('Thiếu số điện thoại', 422);
    }

    // Ưu tiên dùng service nếu bạn đã có, fallback Model để độc lập
    $kh = method_exists($this->khachHangService, 'findByPhone')
      ? $this->khachHangService->findByPhone($phone)
      : KhachHang::with('loaiKhachHang:id,ten_loai_khach_hang')
          ->where('so_dien_thoai', $phone)->first();

    if (!$kh) {
      return CustomResponse::success(['found' => false]);
    }

    return CustomResponse::success([
      'found'               => true,
      'id'                  => $kh->id,
      'code'                => $kh->ma_khach_hang,
      'name'                => $kh->ten_khach_hang,
      // BỎ 'tier' (hạng thành viên). Thay bằng loại khách hàng hiện tại:
      'loai_khach_hang_id'  => $kh->loai_khach_hang_id,
      'loai_khach_hang'     => optional($kh->loaiKhachHang)->ten_loai_khach_hang,
      'total'               => (int) ($kh->doanh_thu_tich_luy ?? 0),
      'phone'               => $kh->so_dien_thoai,
      'status'              => ($kh->doanh_thu_tich_luy ?? 0) > 0 ? 'Khách cũ' : 'Tiềm năng',
    ]);
  }

  /**
   * Ép tính lại doanh thu/điểm/hạng (giờ chỉ cập nhật loại KH + ngày cập nhật)
   * POST /api/khach-hang/{id}/recalc
   */
  public function recalc($id)
  {
    $this->totalsService->refresh((int)$id);

    // trả về record sau khi refresh
    $result = $this->khachHangService->getById($id);
    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }
    return CustomResponse::success($result, 'Đã tính lại theo doanh thu');
  }
}
