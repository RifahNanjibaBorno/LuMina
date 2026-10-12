import importlib.util
import pathlib
import tempfile
import unittest
import zipfile

parser_path = pathlib.Path(__file__).parents[2] / "resources/python/resume_parser.py"
spec = importlib.util.spec_from_file_location("resume_parser", parser_path)
parser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(parser)


class ResumeParserTest(unittest.TestCase):
    def test_maps_sections_contacts_and_links(self):
        result = parser.parse("Student Example\nstudent@example.test\n+8801712345678\nEDUCATION\nBSc in CSE\nSKILLS\nLaravel, Python\nEXPERIENCE\nWeb development internship\ngithub.com/student\n")
        self.assertEqual("Student Example", result["name"])
        self.assertEqual("student@example.test", result["email"])
        self.assertEqual("+8801712345678", result["phone"])
        self.assertEqual("BSc in CSE", result["education"])
        self.assertEqual("Laravel, Python", result["skills"])
        self.assertEqual("https://github.com/student", result["github"])

    def test_reads_inline_section_headings(self):
        result = parser.parse("Student Example\nSkills: Laravel, MySQL\nEducation: BSc in CSE")
        self.assertEqual("Laravel, MySQL", result["skills"])
        self.assertEqual("BSc in CSE", result["education"])

    def test_reads_real_docx_xml(self):
        with tempfile.TemporaryDirectory() as directory:
            file = pathlib.Path(directory) / "resume.docx"
            document = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Student Example</w:t></w:r></w:p><w:p><w:r><w:t>Skills: Python</w:t></w:r></w:p></w:body></w:document>'
            with zipfile.ZipFile(file, "w") as archive:
                archive.writestr("word/document.xml", document)
            result = parser.parse(parser.extract_text(file, "docx"))
            self.assertEqual("Student Example", result["name"])
            self.assertEqual("Python", result["skills"])

    def test_reads_real_pdf_text(self):
        from pypdf import PdfWriter
        from pypdf.generic import DecodedStreamObject, DictionaryObject, NameObject
        with tempfile.TemporaryDirectory() as directory:
            file = pathlib.Path(directory) / "resume.pdf"
            writer = PdfWriter()
            page = writer.add_blank_page(width=612, height=792)
            font = DictionaryObject({NameObject("/Type"): NameObject("/Font"), NameObject("/Subtype"): NameObject("/Type1"), NameObject("/BaseFont"): NameObject("/Helvetica")})
            page[NameObject("/Resources")] = DictionaryObject({NameObject("/Font"): DictionaryObject({NameObject("/F1"): writer._add_object(font)})})
            stream = DecodedStreamObject()
            stream.set_data(b"BT /F1 12 Tf 50 740 Td (Student Example) Tj 0 -20 Td (Skills: Python) Tj ET")
            page[NameObject("/Contents")] = writer._add_object(stream)
            writer.write(file)
            result = parser.parse(parser.extract_text(file, "pdf"))
            self.assertEqual("Student Example", result["name"])
            self.assertEqual("Python", result["skills"])

    def test_empty_resume_is_reported_as_unreadable(self):
        self.assertEqual({"text_found": False}, parser.parse("   \n"))

    def test_does_not_guess_a_name_from_a_resume_title(self):
        self.assertNotIn("name", parser.parse("Curriculum Vitae\nSkills: Python"))


if __name__ == "__main__":
    unittest.main()
