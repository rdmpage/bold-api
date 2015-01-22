# BOLD-API
Wrap Barcode of Life Data Systems (BOLD) API

This module creates an API that wraps some of the functionality of the BOLD API. Specifically, for a given barcode (identified by its process_id), this code will use the BOLD Identification engine API http://www.boldsystems.org/index.php/resources/api?type=idengine to retrieve similar sequences, then compute a NJ tree using PAUP. Finally the localities and BINs for the barcode sequences are added to the NEXUS tree file.


