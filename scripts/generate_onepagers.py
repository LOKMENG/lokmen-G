# -*- coding: utf-8 -*-
"""
توليد ثماني وثائق Word (DOCX) — صفحة واحدة لكل إضاءة.
القالب: نموذج معايير التقويم والاعتماد المدرسي 2026 — إعداد المرشد الطلابي.
"""

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_BREAK
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

# ============================ الهوية البصرية ============================
FONT = "Sakkal Majala"          # خط عربي رسمي متوفر في Windows/Office
PRIMARY = "0F4C5C"              # أزرق أخضر داكن (العناوين)
PRIMARY_LIGHT = "D9E4E8"        # خلفيات فاتحة
ACCENT = "B4884D"               # ذهبي هادئ (لمسات)
DARK_TEXT = "1F2D33"
WHITE = "FFFFFF"
BOX_BG = "EEF4F5"

# ============================ البيانات ============================
KICKER = "نموذج معايير التقويم والاعتماد المدرسي للعام 2026م"
META = [
    ("المجال", "نواتج التعلم"),
    ("المعيار", "التطور الشخصي والصحي والاجتماعي"),
    ("المؤشر", "يلتزم المتعلمون بقواعد السلوك والانضباط المدرسي"),
]
TOOLS_LINE = (
    "أدوات التقويم وفق النموذج:   "
    "☑ استبانة المعلم   ☐ استبانة المتعلم   ☐ استبانة الأسرة   "
    "☑ المقابلات   ☑ تحليل الوثائق   ☐ الملاحظة الصفية   ☐ ملاحظة بيئة التعلم"
)
PRACTICE_LINE = (
    "أفضل ممارسة للمرشد الطلابي: جمع شواهد المؤشر دوريًا عبر الأدوات المحددة أعلاه، "
    "وتوثيقها في ملف التحسين المستمر."
)

DOCS = [
    {
        "file": "01-توحيد-الأداء-المؤسسي.docx",
        "title": "الإضاءة الأولى: توحيد الأداء في جميع مؤشرات معيار قيادة العملية التعليمية",
        "idea": (
            "أن يسير العمل الإرشادي والسلوكي في المدرسة وفق مسارٍ موحّد يربط أداء المرشد الطلابي "
            "بمؤشرات قيادة العملية التعليمية؛ فتتحول الأدوار إلى ممارسات موحدة في جميع الفصول والمراحل، "
            "تُقاس بمؤشرات واضحة تضمن العدالة والشفافية واستمرارية الجودة."
        ),
        "roles": [
            ("دليل الإجراءات الموحّد", "يشارك المرشد الطلابي في إعداد دليل إرشادي موحّد يحدد آلية التعامل مع كل مؤشر، ويُوزَّع على المعلمين والوكلاء لضمان تطبيق موحد."),
            ("ربط الأهداف بالمؤشرات", "تُترجم أهداف الإرشاد إلى مؤشرات قياس (نسبة الالتزام، عدد الجلسات، معدلات الغياب والتأخر) مرتبطة بمؤشرات معيار قيادة العملية التعليمية."),
            ("تنسيق الأدوار الدوري", "عقد لقاءات نصف شهرية مع وكلاء المدرسة والمعلمين لتوحيد الفهم والتطبيق، وتبادل الممارسات الناجحة بين الفصول."),
            ("لوحة الأداء الموحّدة", "إعداد لوحة إرشادية موحدة تعرض المؤشرات الأساسية لحظيًا أمام جميع الأطراف المعنية لدعم القرار المبني على البيانات."),
        ],
        "steps": [
            "تحليل مؤشرات المعيار وربط كل مؤشر بدور إرشادي محدد عبر «مصفوفة الأدوار والمسؤوليات».",
            "إعداد دليل الإجراءات ونماذج التوثيق الموحدة (سجل السلوك، محضر الجلسة الإرشادية، نموذج التواصل مع الأسرة).",
            "عقد ورشة افتتاحية للهيئة التعليمية لتوحيد الفهم والتطبيق وتدريبهم على النماذج الموحدة.",
            "المتابعة الشهرية للتطبيق الموحد وإصدار تقرير تحسيني قصير بمؤشرات كمية وشواهد.",
        ],
        "success": [
            "تطبيق الممارسات الموحّدة في جميع الفصول والمراحل دون استثناء.",
            "اكتمال نماذج التوثيق الموحّدة بنسبة تتجاوز 95%.",
            "ارتفاع رضا المعلمين عن وضوح الأدوار إلى أكثر من 85%.",
        ],
        "scenario": "يرصد المرشد تباينًا في تطبيق إجراءات التأخر بين الفصول؛ فيوحّد النماذج، ويعقد لقاءً قصيرًا مع المعلمين، ويقارن المؤشرات أسبوعيًا حتى يتجانس الأداء ويصحّ التقويم.",
    },
    {
        "file": "02-البرامج-التوعوية-للسلوك-والانضباط.docx",
        "title": "الإضاءة الثانية: تعزيز الوعي بالسلوك والانضباط عبر البرامج التوعوية المنظمة",
        "idea": (
            "أن يسبق الوعي الجزاء؛ برامج توعوية مخططة تشرح لائحة قواعد السلوك والمواظبة للمتعلمين "
            "والهيئة التعليمية وأولياء الأمور بلغة واضحة ومدخلات متجددة، فتترسخ القيم وتُرسَّخ الالتزامات "
            "قبل أي إجراء جزائي."
        ),
        "roles": [
            ("برنامج «وعي وانضباط» السنوي", "خطة توعوية متدرجة (لقاءات، بطاقات إرشادية، مقاطع قصيرة، مسابقات) موجهة للمتعلمين في مختلف المراحل."),
            ("توعية أولياء الأمور", "لقاء بداية العام، ونشرة القواعد، وقناة تواصل فعّالة (مجموعة تواصل/منصة) لشرح اللوائح وتوضيح التوقعات."),
            ("حقيبة توعوية للهيئة التعليمية", "إرشادات موحّدة للغة التعامل الإيجابي وإدارة الفصل، ونشر الممارسات الناجحة بين المعلمين."),
            ("الحملات والمناسبات", "أسبوع الانضباط المدرسي، والطابور التوعوي، والفعاليات المرتبطة بالصحة النفسية والسلوك الإيجابي."),
        ],
        "steps": [
            "تحليل الاحتياج التوعوي باستبيان قصير للمعلمين والمتعلمين ورصد الفجوات المعرفية والسلوكية.",
            "بناء الخطة التوعوية بمؤشرات حضور وتفاعل، وتوزيعها على فترات العام الدراسي.",
            "التنفيذ بالشراكة مع الوكلاء والإعلام المدرسي والقادة الطلابيين.",
            "قياس الأثر باختبار معرفي قصير (قبل/بعد) وملاحظة سلوكية ميدانية.",
        ],
        "success": [
            "وصول الرسالة التوعوية إلى أكثر من 90% من المتعلمين والأسر.",
            "انخفاض المخالفات السلوكية المتكررة خلال الفصل الدراسي.",
            "تفاعل أولياء الأمور مع اللقاءات والنشرات التوعوية.",
        ],
        "scenario": "قبل بداية الفصل الثاني يطلق المرشد أسبوع «وعي وانضباط» بلقاءات قصيرة وبطاقات ومسابقات؛ فتتباطأ المخالفات المتكررة، وتزداد مشاركة الأسر في اللقاءات التوعوية.",
    },
    {
        "file": "03-التضمين-في-الخطة-التشغيلية.docx",
        "title": "الإضاءة الثالثة: تضمين الالتزام بقواعد السلوك والمواظبة في الخطة التشغيلية",
        "idea": (
            "أن يتحول الالتزام بالسلوك والمواظبة من مطلبٍ عارض إلى بندٍ منظَّم في الخطة التشغيلية "
            "وبرامج المدرسة وأنشطتها، بأهدافٍ ومسؤولياتٍ وجدولٍ زمنيٍّ ومؤشرات، يتابع تنفيذها جميع "
            "منسوبي المدرسة."
        ),
        "roles": [
            ("بنود إرشادية في الخطة", "صياغة بنود السلوك والمواظبة في الخطة التشغيلية (أهداف، أنشطة، مؤشرات، مسؤول، زمن) وربطها برؤية المدرسة."),
            ("دمج المهارات في الأنشطة", "إدماج المهارات السلوكية والانضباطية في الأنشطة الصفية واللاصفية (النوادي، خدمة المجتمع، الطابور الصباحي)."),
            ("الجدولة والتقويم", "إدراج تقويم فصلي لأنشطة السلوك الإيجابي ضمن التقويم التشغيلي العام للمدرسة."),
            ("التوثيق والشواهد", "بناء ملف شواهد إرشادية (صور، تقارير، سجلات) يُستند إليه في تقارير التحسين المستمر."),
        ],
        "steps": [
            "مراجعة الخطة التشغيلية الحالية ورصد فجوات تضمين السلوك والمواظبة.",
            "إدراج بنود كمية قابلة للقياس (عدد البرامج، نسبة الحضور، معدلات المخالفات).",
            "توزيع الأدوار: المرشد الطلابي، وكيل شؤون الطلاب، المعلمون، الطلاب القادة.",
            "متابعة التنفيذ في اجتماعات الإدارة الدورية وتوثيق الشواهد والمخرجات.",
        ],
        "success": [
            "نسبة تنفيذ الأنشطة السلوكية المقررة في الخطة تتجاوز 90%.",
            "اكتمال ملف الشواهد المرتبط بكل نشاط مخطط.",
            "تحسن المؤشرات السلوكية في التقرير الفصلي للمدرسة.",
        ],
        "scenario": "تُظهر مراجعة الخطة التشغيلية غياب مؤشرات قياس للسلوك؛ فيضيف المرشد بنودًا كمية (نسبة الالتزام، عدد البرامج) ويوزع الأدوار، فيصبح التنفيذ قابلًا للتوثيق والقياس.",
    },
    {
        "file": "04-المتابعة-اليومية-للحضور-والسلوك.docx",
        "title": "الإضاءة الرابعة: المتابعة اليومية للحضور والانصراف والسلوك ومعالجة الانحرافات",
        "idea": (
            "رصدٌ يومي دقيق بالتنسيق مع وكلاء المدرسة والمعلمين؛ لاكتشاف حالات الغياب والتأخر "
            "والمخالفات مبكرًا، ومعالجتها بالتواصل مع أولياء الأمور وفق الأنظمة والتعليمات المنظمة، "
            "مع مراعاة خصوصية الحالات الفردية."
        ),
        "roles": [
            ("لوحة المتابعة اليومية", "تحديث بيانات الحضور والانصراف والمخالفات يوميًا (لوحة رقمية أو سجل منظم) بالتنسيق مع وكيل الشؤون الطلابية."),
            ("بروتوكول تصعيد متفق عليه", "غياب يوم ← تواصل مع الأسرة؛ غياب ثلاثة أيام ← جلسة إرشادية؛ استمرار الحالة ← الإجراءات النظامية المعمول بها."),
            ("قناة تنسيق سريعة", "تنسيق يومي مع الوكلاء والمعلمين عبر مجموعة عمل واستمارة إلكترونية للرصد الفوري."),
            ("الدعم الفردي", "إنشاء ملف متابعة فردي للمتعلمين ذوي الحالات المتكررة، وخطط دعم تعاونية مع الأسرة."),
        ],
        "steps": [
            "توحيد نموذج الرصد اليومي (حضور/تأخر/مخالفات) وتدريب المعلمين على التوثيق الفوري.",
            "تفعيل التواصل مع الأسرة خلال 24 ساعة للحالات المتجددة عبر القنوات الرسمية.",
            "إصدار تقرير أسبوعي مختصر للإدارة يبرز الحالات المزمنة وسبل معالجتها.",
            "تصميم خطط علاجية فردية للحالات المتكررة ومراجعتها شهريًا.",
        ],
        "success": [
            "دقة التوثيق اليومي تتجاوز 95%.",
            "التواصل مع الأسرة خلال 24 ساعة في أغلب الحالات المتجددة.",
            "انخفاض حالات التأخر والغياب المزمن ربع سنويًا.",
        ],
        "scenario": "يظهر سجل الرصد تأخر متعلم أربع مرات في أسبوعين؛ يتواصل المرشد مع الأسرة خلال 24 ساعة، ويعقد جلسة قصيرة، ويتابع التحسن أسبوعيًا حتى تستقر الحالة.",
    },
    {
        "file": "05-برامج-التحفيز-والتكريم.docx",
        "title": "الإضاءة الخامسة: تعزيز السلوك الإيجابي عبر برامج التحفيز والتكريم",
        "idea": (
            "أن الجزاء الإيجابي أثبت أثرًا من الزجر؛ برامج تحفيزية متنوعة تُعلي من قيمة الالتزام "
            "وتُثبّت السلوك السوي عبر تكريم المتعلمين الملتزمين بقواعد السلوك في الطابور الصباحي "
            "والفصول والمنصات المدرسية."
        ),
        "roles": [
            ("برنامج «طالب الأسبوع/الفصل»", "معايير شفافة (الالتزام، المواظبة، الأخلاق، المبادرة) يرشح لها المعلمون وزملاء الصف."),
            ("تنويع أشكال التكريم", "شهادات، أوسمة، لوحة شرف، إعلان على المنصات المدرسية، ومكافآت رمزية تُحفّز دون مبالغة."),
            ("التحفيز الجماعي", "مسابقة الفصول الملتزمة (كأس الانضباط) لتنمية روح الفريق والمسؤولية المشتركة."),
            ("إشراف الطلاب", "إشراك القادة الطلابيين في الترشيح والتحكيم والتنظيم لترسيخ ملكية السلوك الإيجابي."),
        ],
        "steps": [
            "إعلان معايير التكريم ونشرها بشفافية على جميع المتعلمين.",
            "جدولة التكريم (طابور الأحد، لقاء شهري) والالتزام الدقيق بها.",
            "توثيق التكريم ومشاركته مع الأسر لتوسيع أثر التحفيز.",
            "قياس أثر التحفيز عبر استبانة المتعلم/المعلم ومقارنة المؤشرات قبل وبعد.",
        ],
        "success": [
            "ارتفاع نسبة المكرمين من إجمالي المتعلمين فصليًا.",
            "تحسن التزام الفصول المستهدفة بالمقارنة مع بداية العام.",
            "رضا المتعلمين عن عدالة معايير التكريم يتجاوز 85%.",
        ],
        "scenario": "يكرم المرشد في طابور الأحد فصلًا كاملًا التزم بالمواظبة؛ يُوثَّق التكريم ويُرسل للأسر، فتنعكس الملاحظة الإيجابية على انضباط بقية الفصول.",
    },
    {
        "file": "06-إجراءات-المعالجة-والجلسات-الإرشادية.docx",
        "title": "الإضاءة السادسة: إجراءات عادلة لمعالجة المخالفات وتفعيل الجلسات الإرشادية",
        "idea": (
            "معالجة المخالفات بإجراءاتٍ واضحة وعادلة وفق لائحة قواعد السلوك والمواظبة والأنظمة "
            "المنظمة، ممزوجة بالعمل الإرشادي الذي يعالج الأسباب لا الأعراض، ويعيد المتعلم إلى "
            "المسار الصحيح بكرامة ومسؤولية."
        ),
        "roles": [
            ("سلم إجراءات متدرج", "تنبيه ← محضر ← جلسة إرشادية ← إشعار الأسرة ← الإحالة النظامية، موثقًا في سجل مركزي يضمن الشفافية."),
            ("الجلسات الإرشادية الفردية", "خطة قصيرة (3–5 جلسات) تتناول الدوافع، وإدارة الغضب، والمهارات الاجتماعية، وبناء الوعي بالعواقب."),
            ("العدالة الإجرائية", "تطبيق موحّد بغض النظر عن الصف أو الشعبة، مع توثيق كل خطوة، وحق المتعلم في الشرح والاعتراض."),
            ("الشراكة مع الأسرة", "اتفاقية تعهد سلوكية بعد الجلسة، ومتابعة الأسرة للتحسن، ومشاركة المدرسة للنتائج."),
        ],
        "steps": [
            "تعميم سلم الإجراءات على المعلمين والمتعلمين والأسر بوضوح وشفافية.",
            "إعداد دليل الجلسة الإرشادية النموذجية (أهداف، أدوات، خطة متابعة).",
            "توثيق كل حالة في ملف سلوكي منظم يُصان فيه السر المهني.",
            "مراجعة الحالات شهريًا وقياس تحسن السلوك بعد التدخل الإرشادي.",
        ],
        "success": [
            "انخفاض نسبة إعادة المخالفة لدى الحالات المُعالَجة.",
            "اكتمال توثيق الإجراءات وفق السلم المعتمد.",
            "قناعة المتعلمين والأسر بعدالة الإجراءات المتخذة.",
        ],
        "scenario": "متعلم يكرر مخالفة الإزعاج؛ فينفّذ المرشد سلم الإجراءات (تنبيه ← محضر ← جلستان ← اتفاقية أسرية)، وتتراجع إعادة المخالفة في المتابعة الشهرية.",
    },
    {
        "file": "07-بيئة-مدرسة-منظمة-وآمنة.docx",
        "title": "الإضاءة السابعة: تهيئة بيئة مدرسية منظمة وآمنة تعزز الانضباط المدرسي",
        "idea": (
            "البيئة المنظمة تُعلّم الانضباط قبل أي كلمة؛ تنظيم الاصطفاف، وضبط حركة المتعلمين داخل "
            "المدرسة، والمحافظة على نظافة المرافق، ووضوح التعليمات السلوكية في جميع أرجاء المدرسة، "
            "كلها رسائل صامتة تصنع سلوكًا راسخًا."
        ),
        "roles": [
            ("الخريطة السلوكية للمدرسة", "لافتات إرشادية واضحة في الممرات والفناء والكافتيريا والمرافق تذكّر بالقواعد بلغة بصرية محببة."),
            ("تنظيم الطابور والحركة", "خطة اصطفاف ومسارات حركة ومواعيد ذروة، بمناوبات مراقبة منسقة مع الوكلاء."),
            ("النظافة والانضباط البيئي", "حملة «مدرستنا نظيفة» بمسؤوليات طلابية موزعة وتقييم أسبوعي ملموس."),
            ("مناخ آمن ومحمي", "مكافحة التنمر، وصندوق شكاوى آمن، والاستجابة السريعة للحوادث بالتنسيق مع الإدارة."),
        ],
        "steps": [
            "جولة تشخيصية مع الوكلاء لرصد نقاط الضعف البيئية وتحديد الأولويات.",
            "إعداد خطة التنظيم (المسارات، اللافتات، المناوبات) وتنفيذها تدريجيًا.",
            "تدريب الطلاب القادة على قيادة الطابور والتنسيق وضبط حركة الزملاء.",
            "مراجعة أسبوعية للبيئة المدرسية وتعديل الخطة وفق الملاحظات.",
        ],
        "success": [
            "انخفاض الازدحام والحوادث الميدانية في فترات الذروة.",
            "تقييم بصري أسبوعي لنظافة المرافق بمستوى جيد جدًا فأعلى.",
            "التزام المتعلمين بالمسارات والتعليمات السلوكية المعلنة.",
        ],
        "scenario": "ازدحام في ممر الفناء وقت الفسحة؛ فيعيد المرشد ترتيب المسارات ويضع اللافتات ويوزع المناوبات، فتنخفض الحوادث الميدانية خلال أسبوعين.",
    },
    {
        "file": "08-المتابعة-والتقويم-وتطوير-الإجراءات.docx",
        "title": "الإضاءة الثامنة: متابعة وتقويم الالتزام بقواعد السلوك وتطوير الإجراءات الوقائية",
        "idea": (
            "لا استدامة للانضباط دون قياس؛ سجلات السلوك والمواظبة، والتقارير الدورية، وتحليل "
            "المؤشرات تُستثمر لتطوير الإجراءات الوقائية والعلاجية، وتعزيز استمرارية الالتزام عبر "
            "دورة تحسين موثقة."
        ),
        "roles": [
            ("نظام توثيق مركزي", "سجلات منظمة (حضور، مخالفات، جلسات، تكريم) قابلة للتحليل والاسترجاع السريع."),
            ("التقارير الدورية", "أسبوعية للتنفيذ، شهرية للمؤشرات، فصلية لاتخاذ قرارات التحسين."),
            ("تحليل المؤشرات", "رصد أنماط الغياب وأوقات المخالفات والفصول المستهدفة لتفعيل التدخل الاستباقي."),
            ("التحسين المبني على البيانات", "تعديل الخطة التوعوية والوقائية وفق النتائج، ومشاركة التقرير في المجلس المدرسي."),
        ],
        "steps": [
            "إعداد سجل موحّد ودليل استخدام واضح لجميع الأطراف المعنية.",
            "استخراج مؤشرات شهرية (نسبة الالتزام، معدل المخالفات، سرعة المعالجة).",
            "تحليل الأسباب الجذرية للحالات المتكررة وتصميم إجراءات وقائية مستهدفة.",
            "توثيق الإجراءات التحسينية فصليًا في تقرير التحسين المستمر.",
        ],
        "success": [
            "اكتمال التوثيق في السجلات المركزية بنسبة تتجاوز 95%.",
            "تحسّن المؤشرات السلوكية ربع سنويًا بشكل ملموس.",
            "تحول ثقل الإجراءات من العلاجية إلى الوقائية.",
        ],
        "scenario": "يلاحظ المرشد من تحليل السجلات تركّز المخالفات في آخر الحصص؛ فيقترح خطة انتقالية ووقائية، فتتحول المخالفات من معالجةٍ إلى استباقٍ منظم.",
    },
]

# ============================ عناصر الهوية (صور) ============================
import os
import math
from PIL import Image, ImageDraw

ASSETS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "assets")

GREEN = (106, 168, 79, 255)       # أخضر الهوية
GREEN_DK = (78, 138, 56, 255)
NAVY = (31, 78, 121, 255)         # أزرق داكن
BLUE = (46, 117, 182, 255)        # أزرق الشعار
GRAY = (127, 127, 127, 255)
RED = (192, 0, 0, 255)


def _canvas(size):
    return Image.new("RGBA", (size, size), (255, 255, 255, 0))


def make_marks():
    os.makedirs(ASSETS, exist_ok=True)

    # شعار المملكة (تقريبي: دائرة خضراء + نخلة وسيفان)
    size = 240
    img = _canvas(size)
    d = ImageDraw.Draw(img)
    d.ellipse([8, 8, size - 8, size - 8], outline=GREEN_DK, width=8)
    cx, cy = size // 2, int(size * 0.50)
    d.polygon([(cx - 8, size * 0.80), (cx + 8, size * 0.80), (cx + 4, cy), (cx - 4, cy)], fill=GREEN_DK)
    for ang in (200, 235, 270, 305, 340):
        rad = math.radians(ang)
        x2 = cx + math.cos(rad) * size * 0.24
        y2 = cy + math.sin(rad) * size * 0.24
        d.line([(cx, cy), (x2, y2)], fill=GREEN, width=10)
        d.ellipse([x2 - 7, y2 - 7, x2 + 7, y2 + 7], fill=GREEN)
    for y in (size * 0.62, size * 0.70):
        d.line([(size * 0.24, y), (size * 0.76, y)], fill=GREEN_DK, width=9)
        d.polygon([(size * 0.24, y), (size * 0.20, y - 8), (size * 0.20, y + 8)], fill=GREEN_DK)
        d.polygon([(size * 0.76, y), (size * 0.80, y - 8), (size * 0.80, y + 8)], fill=GREEN_DK)
    img.save(os.path.join(ASSETS, "mark_ksa.png"))

    # شعار مدارس أجيال (دائرة زرقاء بلفّة بيضاء)
    size = 240
    img = _canvas(size)
    d = ImageDraw.Draw(img)
    d.ellipse([10, 10, size - 10, size - 10], fill=BLUE)
    d.arc([40, 40, size - 40, size - 40], start=200, end=110, fill=(255, 255, 255, 255), width=16)
    d.arc([70, 70, size - 70, size - 70], start=180, end=90, fill=(255, 255, 255, 255), width=12)
    d.ellipse([size * 0.42, size * 0.42, size * 0.58, size * 0.58], fill=(255, 255, 255, 255))
    d.polygon([(size * 0.62, 24), (size * 0.74, 24), (size * 0.68, 52)], fill=GREEN)
    img.save(os.path.join(ASSETS, "mark_ajyal.png"))

    # معالم خضراء (ماسات) لشعار التنمية المتكاملة
    size = 240
    img = _canvas(size)
    d = ImageDraw.Draw(img)
    pts = [(60, 70, 34), (120, 52, 42), (180, 74, 30), (95, 118, 26), (150, 118, 24)]
    for x, y, r in pts:
        d.polygon([(x, y - r), (x + r, y), (x, y + r), (x - r, y)], fill=GREEN)
    d.line([(40, 168), (200, 168)], fill=BLUE, width=10)
    d.line([(70, 192), (170, 192)], fill=GREEN, width=8)
    img.save(os.path.join(ASSETS, "mark_dev.png"))

    # أيقونات التذييل: ذرة / رسم بياني / شخص
    size = 120
    img = _canvas(size)
    d = ImageDraw.Draw(img)
    cx = cy = size // 2
    d.ellipse([cx - 10, cy - 10, cx + 10, cy + 10], fill=BLUE)
    for ang in (0, 60, 120):
        box = [cx - 48, cy - 22, cx + 48, cy + 22]
        layer = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        ld = ImageDraw.Draw(layer)
        ld.ellipse(box, outline=GREEN, width=7)
        layer = layer.rotate(ang, center=(cx, cy))
        img = Image.alpha_composite(img, layer)
    img.save(os.path.join(ASSETS, "icon_atom.png"))

    img = _canvas(size)
    d = ImageDraw.Draw(img)
    d.rectangle([20, 60, 40, 100], fill=BLUE)
    d.rectangle([50, 40, 70, 100], fill=GREEN)
    d.rectangle([80, 22, 100, 100], fill=NAVY)
    d.line([14, 104, 106, 104], fill=GRAY, width=6)
    img.save(os.path.join(ASSETS, "icon_chart.png"))

    img = _canvas(size)
    d = ImageDraw.Draw(img)
    d.ellipse([42, 16, 78, 52], fill=BLUE)
    d.pieslice([26, 58, 94, 128], start=180, end=360, fill=GREEN)
    img.save(os.path.join(ASSETS, "icon_person.png"))


# ============================ مساعدات XML ============================

FONT = "Calibri Light"


def _insert_before(parent, element, *successors):
    for tag in successors:
        found = parent.find(qn(tag))
        if found is not None:
            found.addprevious(element)
            return element
    parent.append(element)
    return element


PPR_SHD_SUCC = (
    "w:tabs", "w:suppressAutoHyphens", "w:kinsoku", "w:wordWrap", "w:overflowPunct",
    "w:topLinePunct", "w:autoSpaceDE", "w:autoSpaceDN", "w:bidi", "w:adjustRightInd",
    "w:snapToGrid", "w:spacing", "w:ind", "w:contextualSpacing", "w:mirrorIndents",
    "w:suppressOverlap", "w:jc", "w:textDirection", "w:textAlignment",
    "w:textboxTightWrap", "w:outlineLvl", "w:divId", "w:cnfStyle", "w:rPr",
    "w:sectPr", "w:pPrChange",
)
PPR_BIDI_SUCC = (
    "w:adjustRightInd", "w:snapToGrid", "w:spacing", "w:ind", "w:contextualSpacing",
    "w:mirrorIndents", "w:suppressOverlap", "w:jc", "w:textDirection",
    "w:textAlignment", "w:textboxTightWrap", "w:outlineLvl", "w:divId", "w:cnfStyle",
    "w:rPr", "w:sectPr", "w:pPrChange",
)
RPR_RTL_SUCC = (
    "w:cs", "w:em", "w:lang", "w:eastAsianLayout", "w:specVanish", "w:oMath",
)
TCPR_SHD_SUCC = (
    "w:noWrap", "w:tcMar", "w:textDirection", "w:tcFitText", "w:vAlign",
    "w:hideMark", "w:headers",
)
TBLPR_BIDI_SUCC = (
    "w:tblStyleRowBandSize", "w:tblStyleColBandSize", "w:tblW", "w:jc",
    "w:tblCellSpacing", "w:tblInd", "w:tblBorders", "w:shd", "w:tblLayout",
    "w:tblCellMar", "w:tblLook", "w:tblCaption", "w:tblDescription", "w:tblPrChange",
)


def rtl_paragraph(paragraph, align="right"):
    pPr = paragraph._p.get_or_add_pPr()
    bidi = OxmlElement("w:bidi")
    _insert_before(pPr, bidi, *PPR_BIDI_SUCC)
    paragraph.alignment = {
        "right": WD_ALIGN_PARAGRAPH.RIGHT,
        "center": WD_ALIGN_PARAGRAPH.CENTER,
        "left": WD_ALIGN_PARAGRAPH.LEFT,
    }[align]
    return paragraph


def style_run(run, size=10, bold=False, italic=False, color="203864", font=FONT):
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.italic = italic
    run.font.name = font
    run.font.color.rgb = RGBColor.from_string(color)
    rPr = run._r.get_or_add_rPr()
    rFonts = rPr.find(qn("w:rFonts"))
    if rFonts is None:
        rFonts = OxmlElement("w:rFonts")
        _insert_before(rPr, rFonts, "w:b", "w:bCs", "w:i", "w:iCs", "w:caps",
                       "w:smallCaps", "w:strike", "w:dstrike", "w:outline", "w:shadow",
                       "w:emboss", "w:imprint", "w:noProof", "w:snapToGrid", "w:vanish",
                       "w:webHidden", "w:color", "w:spacing", "w:w", "w:kern",
                       "w:position", "w:sz", "w:szCs", "w:highlight", "w:u", "w:effect",
                       "w:bdr", "w:shd", "w:fitText", "w:vertAlign", "w:rtl", "w:cs",
                       "w:em", "w:lang", "w:eastAsianLayout", "w:specVanish", "w:oMath")
    rFonts.set(qn("w:ascii"), font)
    rFonts.set(qn("w:hAnsi"), font)
    rFonts.set(qn("w:cs"), font)
    rtl = rPr.find(qn("w:rtl"))
    if rtl is None:
        rtl = OxmlElement("w:rtl")
        _insert_before(rPr, rtl, *RPR_RTL_SUCC)
    szCs = rPr.find(qn("w:szCs"))
    if szCs is None:
        szCs = OxmlElement("w:szCs")
        _insert_before(rPr, szCs, "w:highlight", "w:u", "w:effect", "w:bdr", "w:shd",
                       "w:fitText", "w:vertAlign", "w:rtl", "w:cs", "w:em", "w:lang",
                       "w:eastAsianLayout", "w:specVanish", "w:oMath")
    szCs.set(qn("w:val"), str(int(size * 2)))
    bCs = rPr.find(qn("w:bCs"))
    if bCs is None:
        bCs = OxmlElement("w:bCs")
        _insert_before(rPr, bCs, "w:i", "w:iCs", "w:caps", "w:smallCaps", "w:strike",
                       "w:dstrike", "w:outline", "w:shadow", "w:emboss", "w:imprint",
                       "w:noProof", "w:snapToGrid", "w:vanish", "w:webHidden", "w:color",
                       "w:spacing", "w:w", "w:kern", "w:position", "w:sz", "w:szCs",
                       "w:highlight", "w:u", "w:effect", "w:bdr", "w:shd", "w:fitText",
                       "w:vertAlign", "w:rtl", "w:cs", "w:em", "w:lang",
                       "w:eastAsianLayout", "w:specVanish", "w:oMath")
    bCs.set(qn("w:val"), "1" if bold else "0")
    return run


def shade_paragraph(paragraph, fill):
    pPr = paragraph._p.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), fill)
    _insert_before(pPr, shd, *PPR_SHD_SUCC)
    return paragraph


def shade_cell(cell, fill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), fill)
    _insert_before(tcPr, shd, *TCPR_SHD_SUCC)
    return cell


def set_cell_margins(cell, top=40, bottom=40, left=80, right=80):
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = OxmlElement("w:tcMar")
    for tag, val in (("top", top), ("left", left), ("bottom", bottom), ("right", right)):
        node = OxmlElement("w:" + tag)
        node.set(qn("w:w"), str(val))
        node.set(qn("w:type"), "dxa")
        tcMar.append(node)
    _insert_before(tcPr, tcMar, "w:textDirection", "w:tcFitText", "w:vAlign",
                   "w:hideMark", "w:headers")
    return cell


def table_bidi(table):
    tblPr = table._tbl.tblPr
    bidi = OxmlElement("w:bidiVisual")
    _insert_before(tblPr, bidi, *TBLPR_BIDI_SUCC)
    return table


def set_table_borders(table, color="B7C9DA", size=4, inside_color=None, none=False):
    tblPr = table._tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    inside_color = inside_color or color
    for edge, col in (("top", color), ("left", color), ("bottom", color),
                      ("right", color), ("insideH", inside_color), ("insideV", inside_color)):
        el = OxmlElement("w:" + edge)
        if none:
            el.set(qn("w:val"), "none")
            el.set(qn("w:sz"), "0")
            el.set(qn("w:color"), "auto")
        else:
            el.set(qn("w:val"), "single")
            el.set(qn("w:sz"), str(size))
            el.set(qn("w:space"), "0")
            el.set(qn("w:color"), col)
        borders.append(el)
    _insert_before(tblPr, borders, "w:shd", "w:tblLayout", "w:tblCellMar",
                   "w:tblLook", "w:tblCaption", "w:tblDescription", "w:tblPrChange")
    return table


def set_cell_borders(cell, edges):
    """edges: dict like {'bottom': ('single', 24, '6AA84F')}"""
    tcPr = cell._tc.get_or_add_tcPr()
    tcBorders = OxmlElement("w:tcBorders")
    for edge, spec in edges.items():
        val, sz, color = spec
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), val)
        el.set(qn("w:sz"), str(sz))
        el.set(qn("w:space"), "0")
        el.set(qn("w:color"), color)
        tcBorders.append(el)
    _insert_before(tcPr, tcBorders, "w:shd", "w:noWrap", "w:tcMar",
                   "w:textDirection", "w:tcFitText", "w:vAlign", "w:hideMark", "w:headers")
    return cell


def paragraph_spacing(paragraph, before=0, after=2, line=1.05):
    pf = paragraph.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing = line
    return paragraph


def add_picture_to_paragraph(paragraph, path, width_cm):
    run = paragraph.add_run()
    run.add_picture(path, width=Cm(width_cm))
    return run


# ============================ الترويسة والتذييل ============================

H_GREEN = "6AA84F"
H_NAVY = "1F4E79"
H_DARK = "203864"
H_GRAY = "595959"
C_LABEL = "DCE6F1"
C_BOX = "F2F7EC"


def build_letterhead(header):
    """ترويسة الصفحة: وزارة التعليم | مدارس أجيال | التنمية المتكاملة + خط الزخرفة."""
    table = header.add_table(rows=1, cols=3, width=Cm(18))
    table_bidi(table)
    set_table_borders(table, none=True)
    table.columns[0].width = Cm(7.0)
    table.columns[1].width = Cm(6.0)
    table.columns[2].width = Cm(5.0)

    # العمود الأيمن: كتلة المملكة
    cell = table.cell(0, 0)
    set_cell_margins(cell, 0, 0, 0, 0)
    inner = cell.add_table(rows=1, cols=2)
    inner.columns[0].width = Cm(5.1)
    inner.columns[1].width = Cm(1.7)
    table_bidi(inner)
    set_table_borders(inner, none=True)
    tcell, mcell = inner.cell(0, 0), inner.cell(0, 1)
    set_cell_margins(tcell, 0, 0, 0, 0)
    set_cell_margins(mcell, 0, 0, 0, 0)
    p = tcell.paragraphs[0]
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=0, line=1.0)
    style_run(p.add_run("المملكة العربية السعودية"), size=10, bold=True, color="2E7D32")
    for line, sz in (("وزارة التعليم", 8.5), ("الإدارة العامة للتعليم", 7.5),
                     ("مكتب التعليم", 7.5)):
        p2 = tcell.add_paragraph()
        rtl_paragraph(p2, "right")
        paragraph_spacing(p2, after=0, line=1.0)
        style_run(p2.add_run(line), size=sz, bold=(sz == 8.5), color=H_DARK)
    pm = mcell.paragraphs[0]
    rtl_paragraph(pm, "center")
    paragraph_spacing(pm, after=0)
    add_picture_to_paragraph(pm, os.path.join(ASSETS, "mark_ksa.png"), 1.5)

    # العمود الأوسط: مدارس أجيال
    cell = table.cell(0, 1)
    set_cell_margins(cell, 0, 0, 0, 0)
    inner = cell.add_table(rows=1, cols=2)
    inner.columns[0].width = Cm(4.0)
    inner.columns[1].width = Cm(1.8)
    table_bidi(inner)
    set_table_borders(inner, none=True)
    tcell, mcell = inner.cell(0, 0), inner.cell(0, 1)
    set_cell_margins(tcell, 0, 0, 0, 0)
    set_cell_margins(mcell, 0, 0, 0, 0)
    p = tcell.paragraphs[0]
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=0, line=1.15)
    style_run(p.add_run("مـدارس أجـيال للبنـات المـتـميزة"), size=11, bold=True, color=H_NAVY)
    pm = mcell.paragraphs[0]
    rtl_paragraph(pm, "center")
    paragraph_spacing(pm, after=0)
    add_picture_to_paragraph(pm, os.path.join(ASSETS, "mark_ajyal.png"), 1.6)

    # العمود الأيسر: التنمية المتكاملة
    cell = table.cell(0, 2)
    set_cell_margins(cell, 0, 0, 0, 0)
    p = cell.paragraphs[0]
    rtl_paragraph(p, "center")
    paragraph_spacing(p, after=0)
    add_picture_to_paragraph(p, os.path.join(ASSETS, "mark_dev.png"), 1.1)
    p2 = cell.add_paragraph()
    rtl_paragraph(p2, "center")
    paragraph_spacing(p2, after=0, line=1.0)
    style_run(p2.add_run("التنمية المتكاملة"), size=9, bold=True, color=H_NAVY)
    p3 = cell.add_paragraph()
    rtl_paragraph(p3, "center")
    paragraph_spacing(p3, after=0, line=1.0)
    style_run(p3.add_run("Integrated Developments"), size=6.5, color=H_GRAY)

    # خط الزخرفة: رفيع + أخضر عريض + رفيع
    line = header.add_table(rows=1, cols=3, width=Cm(18))
    table_bidi(line)
    set_table_borders(line, none=True)
    widths = (3.5, 10.0, 4.5)
    for i, w in enumerate(widths):
        line.columns[i].width = Cm(w)
    specs = (
        {"bottom": ("single", 6, "A6A6A6")},
        {"bottom": ("single", 36, H_GREEN)},
        {"bottom": ("single", 6, H_NAVY)},
    )
    for i, spec in enumerate(specs):
        c = line.cell(0, i)
        set_cell_margins(c, 0, 0, 0, 0)
        set_cell_borders(c, spec)
        pr = c.paragraphs[0]
        paragraph_spacing(pr, after=0, line=0.7)
        style_run(pr.add_run(""), size=2)


def build_footer_strip(footer):
    """تذييل الصفحة: شهادات ISO/AiAA | شعار وأيقونات | تواصل اجتماعي + شريط سفلي."""
    table = footer.add_table(rows=1, cols=3, width=Cm(18))
    table_bidi(table)
    set_table_borders(table, none=True)
    table.columns[0].width = Cm(5.6)
    table.columns[1].width = Cm(7.0)
    table.columns[2].width = Cm(5.4)

    # يمين: ISO + AiAA
    cell = table.cell(0, 0)
    set_cell_margins(cell, 0, 0, 0, 0)
    inner = cell.add_table(rows=1, cols=2)
    inner.columns[0].width = Cm(2.7)
    inner.columns[1].width = Cm(2.7)
    table_bidi(inner)
    set_table_borders(inner, none=True)
    for idx, spec in enumerate((
        ("ISO 9001:2015", "CERTIFIED", "A60000"),
        ("AiAA", "اعتماد دولي", H_NAVY),
    )):
        bc = inner.cell(0, idx)
        set_cell_margins(bc, 20, 20, 40, 40)
        set_cell_borders(bc, {
            "top": ("single", 4, "BFBFBF"), "left": ("single", 4, "BFBFBF"),
            "bottom": ("single", 4, "BFBFBF"), "right": ("single", 4, "BFBFBF")})
        p = bc.paragraphs[0]
        rtl_paragraph(p, "center")
        paragraph_spacing(p, after=0, line=1.0)
        style_run(p.add_run(spec[0]), size=7, bold=True, color=spec[2])
        p2 = bc.add_paragraph()
        rtl_paragraph(p2, "center")
        paragraph_spacing(p2, after=0, line=1.0)
        style_run(p2.add_run(spec[1]), size=5.5, color=H_GRAY)

    # وسط: الشعار والأيقونات
    cell = table.cell(0, 1)
    set_cell_margins(cell, 0, 0, 0, 0)
    p = cell.paragraphs[0]
    rtl_paragraph(p, "center")
    paragraph_spacing(p, after=1, line=1.0)
    style_run(p.add_run("التعليم المتقـدم يبنـي ..."), size=8.5, bold=True, color=H_NAVY)
    icons = cell.add_table(rows=1, cols=3)
    icons.columns[0].width = Cm(2.2)
    icons.columns[1].width = Cm(2.2)
    icons.columns[2].width = Cm(2.2)
    table_bidi(icons)
    set_table_borders(icons, none=True)
    for i, (img, label) in enumerate((
        ("icon_person.png", "المعرفة"), ("icon_chart.png", "التكنولوجيا"), ("icon_atom.png", "العلوم"),
    )):
        ic = icons.cell(0, i)
        set_cell_margins(ic, 0, 0, 0, 0)
        pi = ic.paragraphs[0]
        rtl_paragraph(pi, "center")
        paragraph_spacing(pi, after=0, line=1.0)
        add_picture_to_paragraph(pi, os.path.join(ASSETS, img), 0.55)
        pl = ic.add_paragraph()
        rtl_paragraph(pl, "center")
        paragraph_spacing(pl, after=0, line=1.0)
        style_run(pl.add_run(label), size=5.5, color=H_GRAY)

    # يسار: التواصل الاجتماعي
    cell = table.cell(0, 2)
    set_cell_margins(cell, 0, 0, 0, 0)
    p = cell.paragraphs[0]
    rtl_paragraph(p, "center")
    paragraph_spacing(p, after=0, line=1.1)
    style_run(p.add_run("f   X   ◎   "), size=8, bold=True, color=H_NAVY)
    style_run(p.add_run("Ajailyanbu"), size=8, bold=True, color=H_NAVY)
    p2 = cell.add_paragraph()
    rtl_paragraph(p2, "center")
    paragraph_spacing(p2, after=0, line=1.1)
    style_run(p2.add_run("☎   "), size=8, bold=True, color=H_GREEN)
    style_run(p2.add_run("Nabualmarifa.edu.sa"), size=8, color=H_NAVY)

    # الشريط السفلي: أخضر عريض يسار + خط أزرق رفيع
    bars = footer.add_table(rows=1, cols=2, width=Cm(18))
    table_bidi(bars)
    set_table_borders(bars, none=True)
    bars.columns[0].width = Cm(11.5)
    bars.columns[1].width = Cm(6.5)
    c0, c1 = bars.cell(0, 0), bars.cell(0, 1)
    set_cell_margins(c0, 0, 0, 0, 0)
    set_cell_margins(c1, 0, 0, 0, 0)
    set_cell_borders(c0, {"bottom": ("single", 6, H_NAVY)})
    set_cell_borders(c1, {"bottom": ("single", 36, H_GREEN)})
    for c in (c0, c1):
        pr = c.paragraphs[0]
        paragraph_spacing(pr, after=0, line=0.7)
        style_run(pr.add_run(""), size=2)


# ============================ عناصر المتن ============================

def paragraph_spacing2(paragraph, before=0, after=3, line=1.05):
    return paragraph_spacing(paragraph, before, after, line)


def add_title_bar(doc, title):
    table = doc.add_table(rows=1, cols=1)
    table_bidi(table)
    set_table_borders(table, none=True)
    cell = table.cell(0, 0)
    shade_cell(cell, H_NAVY)
    set_cell_margins(cell, 90, 90, 140, 140)
    p = cell.paragraphs[0]
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=0, line=1.1)
    style_run(p.add_run(title), size=12, bold=True, color="FFFFFF")
    return table


def add_meta(doc):
    table = doc.add_table(rows=len(META), cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table_bidi(table)
    set_table_borders(table, color="B7C9DA", size=4, inside_color="B7C9DA")
    for i, (label, value) in enumerate(META):
        row = table.rows[i]
        label_cell, value_cell = row.cells[0], row.cells[1]
        label_cell.width = Cm(3.2)
        value_cell.width = Cm(14.8)
        shade_cell(label_cell, C_LABEL)
        set_cell_margins(label_cell, 30, 30, 80, 80)
        set_cell_margins(value_cell, 30, 30, 80, 80)
        p = label_cell.paragraphs[0]
        rtl_paragraph(p, "right")
        paragraph_spacing(p, after=0)
        style_run(p.add_run(label), size=9, bold=True, color=H_NAVY)
        p2 = value_cell.paragraphs[0]
        rtl_paragraph(p2, "right")
        paragraph_spacing(p2, after=0)
        style_run(p2.add_run(value), size=9.5, bold=(i == 2), color=H_DARK)
    return table


def add_section_heading(doc, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, before=5, after=3)
    shade_paragraph(p, H_GREEN)
    pf = p.paragraph_format
    pf.left_indent = Cm(0.12)
    pf.right_indent = Cm(0.12)
    style_run(p.add_run("  " + text), size=10.5, bold=True, color="FFFFFF")
    return p


def add_bullet(doc, marker, lead, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=2, line=1.12)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.35)
    pf.first_line_indent = Cm(-0.35)
    style_run(p.add_run(marker + " "), size=10, bold=True, color=H_GREEN)
    style_run(p.add_run(lead + ": "), size=10, bold=True, color=H_NAVY)
    style_run(p.add_run(text), size=10, color=H_DARK)
    return p


def add_step(doc, idx, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=2, line=1.12)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.5)
    pf.first_line_indent = Cm(-0.5)
    style_run(p.add_run(str(idx) + " - "), size=10, bold=True, color=H_GREEN)
    style_run(p.add_run(text), size=10, color=H_DARK)
    return p


def add_box(doc, lines, fill=C_BOX, border=H_GREEN):
    table = doc.add_table(rows=1, cols=1)
    table_bidi(table)
    set_table_borders(table, color=border, size=4)
    cell = table.cell(0, 0)
    shade_cell(cell, fill)
    set_cell_margins(cell, 50, 50, 100, 100)
    first = True
    for text, size, bold, color in lines:
        p = cell.paragraphs[0] if first else cell.add_paragraph()
        first = False
        rtl_paragraph(p, "right")
        paragraph_spacing(p, after=1, line=1.1)
        style_run(p.add_run(text), size=size, bold=bold, color=color)
    return table


def page_break(doc):
    p = doc.add_paragraph()
    run = p.add_run()
    run.add_break(WD_BREAK.PAGE)
    return p


def add_index_item(doc, num, title):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.12)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.7)
    pf.first_line_indent = Cm(-0.7)
    style_run(p.add_run("الإضاءة " + str(num) + " "), size=10.5, bold=True, color=H_GREEN)
    style_run(p.add_run("— " + title), size=10.5, color=H_DARK)
    return p


# ============================ بناء الوثيقة ============================

def setup_document(doc):
    section = doc.sections[0]
    section.page_width = Cm(21.0)
    section.page_height = Cm(29.7)
    section.top_margin = Cm(3.55)
    section.bottom_margin = Cm(3.15)
    section.left_margin = Cm(1.5)
    section.right_margin = Cm(1.5)
    section.header_distance = Cm(0.7)
    section.footer_distance = Cm(0.6)

    normal = doc.styles["Normal"]
    normal.font.name = FONT
    normal.font.size = Pt(10)
    rpr = normal.element.get_or_add_rPr()
    rFonts = rpr.find(qn("w:rFonts"))
    if rFonts is None:
        rFonts = OxmlElement("w:rFonts")
        rpr.append(rFonts)
    rFonts.set(qn("w:ascii"), FONT)
    rFonts.set(qn("w:hAnsi"), FONT)
    rFonts.set(qn("w:cs"), FONT)

    build_letterhead(section.header)
    build_footer_strip(section.footer)
    return section


def populate_content(doc, doc_data):
    add_title_bar(doc, doc_data["title"])
    add_meta(doc)

    add_section_heading(doc, "أولًا: الفكرة المحورية")
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.15)
    style_run(p.add_run(doc_data["idea"]), size=10.5, color=H_DARK)

    add_section_heading(doc, "ثانيًا: إثراء أدوار المرشد الطلابي")
    for lead, text in doc_data["roles"]:
        add_bullet(doc, "◆", lead, text)

    add_section_heading(doc, "ثالثًا: خطوات التنفيذ الموصى بها")
    for i, text in enumerate(doc_data["steps"], start=1):
        add_step(doc, i, text)

    add_section_heading(doc, "رابعًا: مؤشرات نجاح التحقيق")
    for text in doc_data["success"]:
        add_bullet(doc, "✓", "مؤشر", text)

    add_section_heading(doc, "خامسًا: سيناريو تطبيقي للمرشد الطلابي")
    add_box(doc, [(doc_data["scenario"], 9.5, False, H_DARK)])

    add_box(doc, [
        (TOOLS_LINE, 8.5, True, H_NAVY),
        (PRACTICE_LINE, 8.5, False, H_DARK),
    ], fill="EDF3F8", border=H_NAVY)


def set_props(doc, title):
    props = doc.core_properties
    props.title = title
    props.author = "المرشد الطلابي"
    props.subject = "نموذج معايير التقويم والاعتماد المدرسي 2026 — نواتج التعلم"
    props.comments = "المؤشر: يلتزم المتعلمون بقواعد السلوك والانضباط المدرسي"


def build(doc_data, idx):
    doc = Document()
    setup_document(doc)
    populate_content(doc, doc_data)
    set_props(doc, doc_data["title"])
    return doc


def build_cover(doc):
    add_title_bar(doc, "الالتزام بقواعد السلوك والانضباط المدرسي — الإضاءات الثمانية")
    add_meta(doc)

    add_section_heading(doc, "عن هذه الوثيقة")
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.18)
    style_run(p.add_run(
        "دليل تطبيقي موحّد للمرشد الطلابي يجمع الإضاءات الثمانية للمؤشر "
        "«يلتزم المتعلمون بقواعد السلوك والانضباط المدرسي»، مطوّرة وممتدّة إلى أدوار "
        "وخطوات ومؤشرات نجاح وسيناريوهات تطبيقية، وفق متطلبات نموذج معايير التقويم "
        "والاعتماد المدرسي للعام 2026م. كل إضاءة في صفحة مستقلة بعنوانها."
    ), size=10.5, color=H_DARK)

    add_box(doc, [
        ("بيانات التوثيق (تُستكمل يدويًا):", 9, True, H_NAVY),
        ("المدرسة: ......................................    |    "
         "المرشد الطلابي: ......................................    |    "
         "التاريخ: ......... / ......... / 1447هـ", 9.5, False, H_DARK),
    ])

    add_section_heading(doc, "فهرس الإضاءات الثمانية")
    for i, data in enumerate(DOCS, start=1):
        title = data["title"].split(": ", 1)[-1]
        add_index_item(doc, i, title)

    add_box(doc, [
        (TOOLS_LINE, 8.5, True, H_NAVY),
        (PRACTICE_LINE, 8.5, False, H_DARK),
    ], fill="EDF3F8", border=H_NAVY)


def build_combined():
    doc = Document()
    setup_document(doc)
    build_cover(doc)
    for data in DOCS:
        page_break(doc)
        populate_content(doc, data)
    set_props(doc, "الوثيقة الموحدة — إضاءات السلوك والانضباط المدرسي (8 صفحات)")
    return doc


def main():
    make_marks()
    out_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "docs")
    os.makedirs(out_dir, exist_ok=True)
    for i, data in enumerate(DOCS, start=1):
        doc = build(data, i)
        path = os.path.join(out_dir, data["file"])
        doc.save(path)
        print("[{}/8] {}".format(i, path))
    combined = build_combined()
    combined_path = os.path.join(out_dir, "00-الوثيقة-الموحدة-الإضاءات-الثمانية.docx")
    combined.save(combined_path)
    print("[موحد] " + combined_path)


if __name__ == "__main__":
    main()
