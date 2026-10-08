# -*- coding: utf-8 -*-
"""
توليد ثماني وثائق Word (DOCX) — صفحة واحدة لكل إضاءة.
القالب: نموذج معايير التقويم والاعتماد المدرسي 2026 — إعداد المرشد الطلابي.
"""

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
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

# ============================ مساعدات XML ============================

def _insert_before(parent, element, *successors):
    """إدراج عنصر في موضعه الصحيح حسب ترتيب مخطط OOXML."""
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
SECTPR_PGBORDERS_SUCC = (
    "w:lnNumType", "w:pgNumType", "w:cols", "w:formProt", "w:vAlign", "w:noEndnote",
    "w:titlePg", "w:textDirection", "w:bidi", "w:rtlGutter", "w:docGrid",
    "w:printerSettings", "w:sectPrChange",
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


def style_run(run, size=10, bold=False, italic=False, color=DARK_TEXT, font=FONT):
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


def set_cell_margins(cell, top=60, bottom=60, left=100, right=100):
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = OxmlElement("w:tcMar")
    for tag, val in (("top", top), ("left", left), ("bottom", bottom), ("right", right)):
        node = OxmlElement(f"w:{tag}")
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


def set_table_borders(table, color=PRIMARY, size=6, inside_color=None):
    tblPr = table._tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    inside_color = inside_color or color
    for edge, col in (("top", color), ("left", color), ("bottom", color),
                      ("right", color), ("insideH", inside_color), ("insideV", inside_color)):
        el = OxmlElement(f"w:{edge}")
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), str(size))
        el.set(qn("w:space"), "0")
        el.set(qn("w:color"), col)
        borders.append(el)
    _insert_before(tblPr, borders, "w:shd", "w:tblLayout", "w:tblCellMar",
                   "w:tblLook", "w:tblCaption", "w:tblDescription", "w:tblPrChange")
    return table


def paragraph_spacing(paragraph, before=0, after=3, line=1.05):
    pf = paragraph.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing = line
    return paragraph


def add_page_border(section, color=PRIMARY):
    sectPr = section._sectPr
    pgBorders = OxmlElement("w:pgBorders")
    pgBorders.set(qn("w:offsetFrom"), "page")
    for edge in ("top", "left", "bottom", "right"):
        el = OxmlElement(f"w:{edge}")
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), "8")
        el.set(qn("w:space"), "20")
        el.set(qn("w:color"), color)
        pgBorders.append(el)
    _insert_before(sectPr, pgBorders, *SECTPR_PGBORDERS_SUCC)


# ============================ عناصر التصميم ============================

def add_header(doc, title):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table_bidi(table)
    set_table_borders(table, color=PRIMARY, size=2)
    cell = table.cell(0, 0)
    shade_cell(cell, PRIMARY)
    set_cell_margins(cell, top=120, bottom=120, left=160, right=160)

    p = cell.paragraphs[0]
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=1)
    style_run(p.add_run(KICKER), size=9.5, bold=False, color="C9DDE2")

    p2 = cell.add_paragraph()
    rtl_paragraph(p2, "right")
    paragraph_spacing(p2, before=1, after=0, line=1.0)
    style_run(p2.add_run(title), size=14, bold=True, color=WHITE)
    return table


def add_meta(doc):
    spacer = doc.add_paragraph()
    paragraph_spacing(spacer, after=0, before=2)
    spacer_run = spacer.add_run("")
    style_run(spacer_run, size=4)

    table = doc.add_table(rows=len(META), cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table_bidi(table)
    set_table_borders(table, color=PRIMARY, size=4, inside_color="9FB8BF")
    for i, (label, value) in enumerate(META):
        row = table.rows[i]
        label_cell, value_cell = row.cells[0], row.cells[1]
        shade_cell(label_cell, PRIMARY_LIGHT)
        set_cell_margins(label_cell, top=50, bottom=50, left=100, right=100)
        set_cell_margins(value_cell, top=50, bottom=50, left=100, right=100)

        p = label_cell.paragraphs[0]
        rtl_paragraph(p, "right")
        paragraph_spacing(p, after=0)
        style_run(p.add_run(label), size=9.5, bold=True, color=PRIMARY)

        p2 = value_cell.paragraphs[0]
        rtl_paragraph(p2, "right")
        paragraph_spacing(p2, after=0)
        style_run(p2.add_run(value), size=10, bold=(i == 2), color=DARK_TEXT)
    return table


def add_section_heading(doc, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, before=8, after=4)
    shade_paragraph(p, PRIMARY)
    pf = p.paragraph_format
    pf.left_indent = Cm(0.15)
    pf.right_indent = Cm(0.15)
    style_run(p.add_run(f"  {text}"), size=11, bold=True, color=WHITE)
    return p


def add_bullet(doc, marker, lead, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.15)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.35)
    pf.first_line_indent = Cm(-0.35)
    style_run(p.add_run(f"{marker} "), size=10.5, bold=True, color=ACCENT)
    style_run(p.add_run(f"{lead}: "), size=10.5, bold=True, color=PRIMARY)
    style_run(p.add_run(text), size=10.5, color=DARK_TEXT)
    return p


def add_step(doc, idx, text):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.15)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.5)
    pf.first_line_indent = Cm(-0.5)
    style_run(p.add_run(f"{idx} - "), size=10.5, bold=True, color=ACCENT)
    style_run(p.add_run(text), size=10.5, color=DARK_TEXT)
    return p


def add_box(doc, lines):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table_bidi(table)
    set_table_borders(table, color=ACCENT, size=4)
    cell = table.cell(0, 0)
    shade_cell(cell, BOX_BG)
    set_cell_margins(cell, top=70, bottom=70, left=120, right=120)
    first = True
    for text, size, bold, color in lines:
        p = cell.paragraphs[0] if first else cell.add_paragraph()
        first = False
        rtl_paragraph(p, "right")
        paragraph_spacing(p, after=2, line=1.1)
        style_run(p.add_run(text), size=size, bold=bold, color=color)
    return table


def add_footer(section, idx):
    footer = section.footer
    p = footer.paragraphs[0]
    rtl_paragraph(p, "center")
    paragraph_spacing(p, before=2, after=0)
    text = f"إعداد: المرشد الطلابي — وثيقة العمل الإرشادي | الإضاءة {idx} من 8 | العام الدراسي 2026م"
    style_run(p.add_run(text), size=8, color="6B7B80")


# ============================ بناء الوثيقة ============================

def setup_document(doc):
    """إعداد الصفحة والنمط الافتراضي."""
    section = doc.sections[0]
    section.page_width = Cm(21.0)
    section.page_height = Cm(29.7)
    section.top_margin = Cm(1.1)
    section.bottom_margin = Cm(1.1)
    section.left_margin = Cm(1.25)
    section.right_margin = Cm(1.25)
    section.footer_distance = Cm(0.5)
    add_page_border(section)

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
    return section


def populate_content(doc, doc_data):
    """محتوى صفحة الإضاءة الواحدة."""
    add_header(doc, doc_data["title"])
    add_meta(doc)

    add_section_heading(doc, "أولًا: الفكرة المحورية")
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=4, line=1.2)
    style_run(p.add_run(doc_data["idea"]), size=11, color=DARK_TEXT)

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
    add_box(doc, [
        (doc_data["scenario"], 9.5, False, DARK_TEXT),
    ])

    add_box(doc, [
        (TOOLS_LINE, 9, True, PRIMARY),
        (PRACTICE_LINE, 9, False, DARK_TEXT),
    ])


def set_props(doc, title):
    props = doc.core_properties
    props.title = title
    props.author = "المرشد الطلابي"
    props.subject = "نموذج معايير التقويم والاعتماد المدرسي 2026 — نواتج التعلم"
    props.comments = "المؤشر: يلتزم المتعلمون بقواعد السلوك والانضباط المدرسي"


def page_break(doc):
    p = doc.add_paragraph()
    run = p.add_run()
    run.add_break(WD_BREAK.PAGE)
    return p


def add_index_item(doc, num, title):
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=3, line=1.15)
    pf = p.paragraph_format
    pf.right_indent = Cm(0.7)
    pf.first_line_indent = Cm(-0.7)
    style_run(p.add_run(f"الإضاءة {num} "), size=10.5, bold=True, color=ACCENT)
    style_run(p.add_run(f"— {title}"), size=10.5, color=DARK_TEXT)
    return p


def build(doc_data, idx):
    """وثيقة مستقلة بصفحة واحدة لكل إضاءة."""
    doc = Document()
    section = setup_document(doc)
    populate_content(doc, doc_data)
    add_footer(section, f"الإضاءة {idx} من 8")
    set_props(doc, doc_data["title"])
    return doc


def build_cover(doc):
    """صفحة الغلاف والفهرس للوثيقة الموحدة."""
    add_header(doc, "الالتزام بقواعد السلوك والانضباط المدرسي — الإضاءات الثمانية")
    add_meta(doc)

    add_section_heading(doc, "عن هذه الوثيقة")
    p = doc.add_paragraph()
    rtl_paragraph(p, "right")
    paragraph_spacing(p, after=4, line=1.2)
    style_run(
        p.add_run(
            "دليل تطبيقي موحّد للمرشد الطلابي يجمع الإضاءات الثمانية للمؤشر "
            "«يلتزم المتعلمون بقواعد السلوك والانضباط المدرسي»، مطوّرة وممتدّة إلى أدوار "
            "وخطوات ومؤشرات نجاح وسيناريوهات تطبيقية، وفق متطلبات نموذج معايير التقويم "
            "والاعتماد المدرسي للعام 2026م. كل إضاءة في صفحة مستقلة بعنوانها."
        ),
        size=11,
        color=DARK_TEXT,
    )

    add_section_heading(doc, "فهرس الإضاءات الثمانية")
    for i, data in enumerate(DOCS, start=1):
        title = data["title"].split(": ", 1)[-1]
        add_index_item(doc, i, title)

    add_box(doc, [
        (TOOLS_LINE, 9, True, PRIMARY),
        (PRACTICE_LINE, 9, False, DARK_TEXT),
    ])


def build_combined():
    """الوثيقة الموحدة: غلاف + فهرس ثم الإضاءات الثمانية، كل إضاءة في صفحة."""
    doc = Document()
    section = setup_document(doc)
    add_footer(section, "الوثيقة الموحدة — الإضاءات الثمانية")
    build_cover(doc)
    for data in DOCS:
        page_break(doc)
        populate_content(doc, data)
    set_props(doc, "الوثيقة الموحدة — إضاءات السلوك والانضباط المدرسي (8 صفحات)")
    return doc


def main():
    import os
    out_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "docs")
    os.makedirs(out_dir, exist_ok=True)
    for i, data in enumerate(DOCS, start=1):
        doc = build(data, i)
        path = os.path.join(out_dir, data["file"])
        doc.save(path)
        print(f"[{i}/8] {path}")

    combined = build_combined()
    combined_path = os.path.join(out_dir, "00-الوثيقة-الموحدة-الإضاءات-الثمانية.docx")
    combined.save(combined_path)
    print(f"[موحد] {combined_path}")


if __name__ == "__main__":
    main()
