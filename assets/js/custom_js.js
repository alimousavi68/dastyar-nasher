


const Toast = Swal.mixin({
    toast: true,
    position: "bottom-start",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});



// <!-- setup select2  -->

$(document).ready(function () {
    $('#category_list').select2({
        closeOnSelect: false,
        placeholder: "دسته بندی ها",
        allowClear: true,
        width: '100%',
        minimumInputLength: 0 // حداقل تعداد کاراکترها برای شروع جستجو
    });
    $('#resource_list').select2({
        closeOnSelect: false,
        placeholder: "منابع",
        allowClear: true,
        width: '100%',
        minimumInputLength: 0 // حداقل تعداد کاراکترها برای شروع جستجو
    });
});


// // <!-- enable toltips -->

document.addEventListener("DOMContentLoaded", function () {
    // فعال‌سازی تولتیپ‌ها
    var tooltipTriggerEl = document.getElementById('clear_filters');
    var tooltip = new bootstrap.Tooltip(tooltipTriggerEl);
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // تابع پاکسازی فیلترها
    document.querySelector('.btn-outline-secondary').addEventListener('click', function () {
        // پاک کردن مقادیر ورودی
        document.getElementById('search_keyword').value = '';

        // پاک کردن انتخاب‌های select2
        $('#category_list').val(null).trigger('change');
        $('#resource_list').val(null).trigger('change');
    });
});


// <!-- clear all filters -->

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
