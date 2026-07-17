<?php

namespace App\Http\Resources\API;

use App\Helper\ServiceHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ServicePackage;
use Illuminate\Support\Str;
class ServiceDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // ✅ Language detect + set
        $lang = substr(request()->header('Accept-Language', 'en'), 0, 2);
        app()->setLocale($lang);
        $user_id = request()->customer_id;
        $image = getSingleMedia($this,'service_attachment', null);
        $file_extention = config('constant.IMAGE_EXTENTIONS');
        $extention = in_array(strtolower(imageExtention($image)),$file_extention);
        $calculateFinalPrice = ServiceHelper::serviceFinalPrice($this->id);

        // ✅ Category Translation
        $categoryKey = Str::slug(optional($this->category)->name, '_');
        $categoryName = __('categorylang.' . $categoryKey);

        if ($categoryName === 'categorylang.' . $categoryKey) {
            $categoryName = optional($this->category)->name;
        }

        // ✅ Subcategory Translation
        $subcategoryKey = Str::slug(optional($this->subcategory)->name, '_');
        $subcategoryName = __('subcategorylang.' . $subcategoryKey);

        if ($subcategoryName === 'subcategorylang.' . $subcategoryKey) {
            $subcategoryName = optional($this->subcategory)->name;
        }

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'category_id'   => $this->category_id,
            'subcategory_id'   => $this->subcategory_id,
            'provider_id'   => $this->provider_id,
            'price'         => $this->price,
            'price_format'  => getPriceFormat($this->price),
            'type'          => $this->type,
            'discount'      => $this->discount,
            'duration'      => $this->duration,
            'status'        => $this->status,
            'description'   => $this->description,
            'is_featured'   => $this->is_featured,
            'provider_name' => optional($this->providers)->name,
//            'category_name'  => optional($this->category)->name,
//            'subcategory_name'  => optional($this->subcategory)->name,
            'category_name'     => $categoryName,
            'subcategory_name'  => $subcategoryName,
            'attchments' => getAttachments($this->getMedia('service_attachment'),null),
            'attchments_array' => getAttachmentArray($this->getMedia('service_attachment'),null),
            'total_review'  => $this->serviceRating->count('id'),
            'total_rating'  => count($this->serviceRating) > 0 ? (float) number_format(max($this->serviceRating->avg('rating'),0), 2) : 0,
            'is_favourite'  => $this->getUserFavouriteService->where('user_id',$user_id)->first() ? 1 : 0,
            'service_address_mapping' => $this->providerServiceAddress,
            'attchment_extension' => $extention,
            'deleted_at' => $this->deleted_at,
            'is_slot'           => $this->is_slot,
            'slots'              => getServiceTimeSlot($this->provider_id, request('date')),
            'servicePackage'    => ServicePackageResource::collection(ServicePackage::whereIn('id',$this->servicePackage->pluck('service_package_id'))->where('status',1)->get()),
            'visit_type'           => $this->visit_type,
            'is_enable_advance_payment' => $this->is_enable_advance_payment,
            'advance_payment_amount' => $this->advance_payment_amount== null ? 0:(double) $this->advance_payment_amount,
            'final_price' => floatval(number_format($calculateFinalPrice,2,'.')),
            'final_display_price' => getPriceFormat($calculateFinalPrice),
        ];
    }
}
